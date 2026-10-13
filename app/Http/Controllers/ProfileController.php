<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Services\Ai\AiException;
use App\Services\Ai\AiProvider;
use App\Services\WebsiteReader;
use App\Services\WebsiteReadException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $profile = BusinessProfile::current();

        return view('profile.show', [
            'profile' => $profile,
            'showManual' => session('show_manual') || $request->boolean('manual'),
            'showAnalyse' => ! $profile || $request->boolean('reanalyse'),
        ]);
    }

    public function analyse(Request $request, WebsiteReader $reader): RedirectResponse
    {
        $data = $this->validateUrls($request);

        try {
            $result = $reader->read($data['website_url']);
        } catch (WebsiteReadException $e) {
            return redirect()->route('profile.show')
                ->withInput()
                ->with('error', 'The website could not be read: '.$e->getMessage().' You can enter the profile manually below.')
                ->with('show_manual', true);
        }

        try {
            $draft = app(AiProvider::class)->profile($result['facts'], $result['text']);
        } catch (AiException $e) {
            return redirect()->route('profile.show')->withInput()->with('error', 'AI profile drafting failed: '.$e->getMessage());
        }

        $fields = [];
        foreach (array_keys(BusinessProfile::FIELDS) as $key) {
            if (isset($result['facts'][$key])) {
                $fields[$key] = ['provenance' => 'verified'] + $result['facts'][$key];
            } elseif (isset($draft->fields[$key])) {
                $fields[$key] = ['value' => $draft->fields[$key], 'provenance' => 'assumption', 'source' => $draft->provenance, 'model' => $draft->model];
            }
        }

        $this->save([
            'website_url' => $result['url'],
            'social_urls' => $data['social_urls'],
            'fields' => $fields,
            'sources' => ['pages' => $result['pages'], 'skipped' => $result['skipped'], 'retrieved_at' => $result['retrieved_at']],
            'ai_provenance' => $draft->provenance.($draft->model ? ':'.$draft->model : ''),
        ]);

        return redirect()->route('profile.show')
            ->with('status', 'Read '.count($result['pages']).' pages from the website. Review the profile, edit anything that is wrong, then confirm it.');
    }

    public function manual(Request $request): RedirectResponse
    {
        $data = $this->validateUrls($request);
        $values = $this->validateFields($request);

        $fields = [];
        foreach ($values as $key => $value) {
            if ($value !== '') {
                $fields[$key] = ['value' => $value, 'provenance' => 'manual'];
            }
        }

        $this->save([
            'website_url' => $data['website_url'],
            'social_urls' => $data['social_urls'],
            'fields' => $fields,
            'sources' => ['pages' => [], 'skipped' => [], 'retrieved_at' => null, 'manual' => true],
            'ai_provenance' => null,
        ]);

        return redirect()->route('profile.show')->with('status', 'Profile saved from manual entry. Review it, then confirm.');
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = BusinessProfile::current() ?? abort(404);
        $values = $this->validateFields($request);
        $wasConfirmed = $profile->isConfirmed();

        $fields = $profile->fields;
        $changed = 0;
        foreach ($values as $key => $value) {
            if ($value === ($fields[$key]['value'] ?? '')) {
                continue;
            }
            $changed++;
            if ($value === '') {
                unset($fields[$key]);
            } else {
                $fields[$key] = ['value' => $value, 'provenance' => 'edited', 'previous_provenance' => $fields[$key]['provenance'] ?? null];
            }
        }

        if ($changed === 0) {
            return redirect()->route('profile.show')->with('status', 'No changes to save.');
        }

        $profile->update(['fields' => $fields, 'status' => BusinessProfile::DRAFT, 'confirmed_at' => null]);

        return redirect()->route('profile.show')->with('status', "Saved {$changed} edited field(s).".($wasConfirmed ? ' The profile is back in draft: confirm it again to continue.' : ''));
    }

    public function confirm(): RedirectResponse
    {
        $profile = BusinessProfile::current() ?? abort(404);
        if ($profile->value('business_name') === '') {
            return redirect()->route('profile.show')->with('error', 'Add a business name before confirming the profile.');
        }

        $profile->update(['status' => BusinessProfile::CONFIRMED, 'confirmed_at' => now()]);

        return redirect()->route('profile.show')->with('status', 'Profile confirmed. Step 2 (round-one ideas) can now use it.');
    }

    /** @return array{website_url: string, social_urls: array<string, string>} */
    private function validateUrls(Request $request): array
    {
        $rules = ['website_url' => ['required', 'string', 'max:2048', 'url:http,https']];
        foreach (array_keys(BusinessProfile::SOCIAL_PLATFORMS) as $platform) {
            $rules["social_urls.{$platform}"] = ['nullable', 'string', 'max:2048', 'url:http,https'];
        }
        $data = $request->validate($rules, ['website_url.url' => 'Enter a full website address starting with http:// or https://.']);

        return ['website_url' => $data['website_url'], 'social_urls' => array_filter($data['social_urls'] ?? [])];
    }

    /** @return array<string, string> */
    private function validateFields(Request $request): array
    {
        $rules = [];
        foreach (array_keys(BusinessProfile::FIELDS) as $key) {
            $rules["fields.{$key}"] = ['nullable', 'string', 'max:5000'];
        }
        $data = $request->validate($rules);

        return collect(array_keys(BusinessProfile::FIELDS))
            ->mapWithKeys(fn ($key) => [$key => trim(str_replace("\r\n", "\n", (string) ($data['fields'][$key] ?? '')))])
            ->all();
    }

    private function save(array $attributes): BusinessProfile
    {
        $attributes += ['status' => BusinessProfile::DRAFT, 'confirmed_at' => null];
        $profile = BusinessProfile::current();

        return $profile ? tap($profile)->update($attributes) : BusinessProfile::create($attributes);
    }
}
