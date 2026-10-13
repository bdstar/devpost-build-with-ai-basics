<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\ContentRound;
use App\Models\KnowledgeRecord;
use App\Services\Ai\AiException;
use App\Services\Ai\AiProvider;
use App\Services\RoundComparison;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IdeasController extends Controller
{
    public function index(Request $request, RoundComparison $comparison): View
    {
        $profile = BusinessProfile::current();
        $roundOne = ContentRound::latestNumber(1);
        $roundTwo = ContentRound::latestNumber(2);

        $filters = array_filter($request->only(['platform', 'campaign_goal', 'format']));
        $roundOneIdeas = $roundOne?->ideas()->get() ?? collect();

        return view('workflow.index', [
            'profile' => $profile,
            'roundOne' => $roundOne,
            'roundOneIdeas' => $roundOneIdeas->filter(fn ($idea) => collect($filters)->every(fn ($v, $k) => $idea->$k === $v)),
            'filterOptions' => collect(['platform', 'campaign_goal', 'format'])->mapWithKeys(fn ($f) => [$f => $roundOneIdeas->pluck($f)->unique()->sort()->values()]),
            'filters' => $filters,
            'roundTwo' => $roundTwo,
            'roundTwoIdeas' => $roundTwo?->ideas()->get() ?? collect(),
            'knowledgeCount' => KnowledgeRecord::count(),
            'comparison' => $roundOne && $roundTwo ? $comparison->compare($roundOne, $roundTwo) : null,
        ]);
    }

    public function generateRoundOne(): RedirectResponse
    {
        $profile = BusinessProfile::current();
        if (! $profile?->isConfirmed()) {
            return redirect()->route('workflow.index')->with('error', 'Confirm the business profile (Step 1) before generating round-one ideas.');
        }
        if (ContentRound::latestNumber(1)) {
            return redirect()->route('workflow.index')->with('error', 'Round one already exists. It is kept so round two can be compared with it.');
        }

        return $this->generate($profile, collect(), 1);
    }

    public function generateRoundTwo(): RedirectResponse
    {
        $profile = BusinessProfile::current();
        if (! $profile?->isConfirmed()) {
            return redirect()->route('workflow.index')->with('error', 'Confirm the business profile (Step 1) before generating round two.');
        }
        if (! ContentRound::latestNumber(1)) {
            return redirect()->route('workflow.index')->with('error', 'Generate round one first, so round two has something to be compared with.');
        }
        $knowledge = KnowledgeRecord::ordered()->get();
        if ($knowledge->isEmpty()) {
            return redirect()->route('workflow.index')->with('error', 'Round two needs knowledge records. Load the dataset and save insights in Performance & Insights first.');
        }

        return $this->generate($profile, $knowledge, 2);
    }

    public function select(Request $request, ContentRound $round): RedirectResponse
    {
        $ids = array_map('intval', (array) $request->input('selected', []));

        DB::transaction(function () use ($round, $ids) {
            $round->ideas()->update(['selected' => false]);
            $round->ideas()->whereIn('id', $ids)->update(['selected' => true]);
        });

        return redirect()->to(route('workflow.index').'#round-'.$round->number)
            ->with('status', 'Selected '.$round->ideas()->where('selected', true)->count()." idea(s) in round {$round->number}.");
    }

    private function generate(BusinessProfile $profile, Collection $knowledge, int $number): RedirectResponse
    {
        try {
            $ideas = app(AiProvider::class)->ideas($profile, $knowledge, $number);
        } catch (AiException $e) {
            return redirect()->route('workflow.index')->with('error', "Round {$number} generation failed: ".$e->getMessage().' Existing work was kept.');
        }

        $knownIds = $knowledge->pluck('id')->all();
        foreach ($ideas as $idea) {
            $unknown = array_diff($idea['influenced_by'] ?? [], $knownIds);
            if ($unknown) {
                return redirect()->route('workflow.index')->with('error', 'Generated ideas referenced knowledge records that do not exist; nothing was saved.');
            }
        }

        DB::transaction(function () use ($profile, $knowledge, $number, $ideas) {
            // Only the latest round two is kept; round one is never replaced.
            if ($number === 2) {
                ContentRound::where('number', 2)->get()->each->delete();
            }

            $round = ContentRound::create([
                'number' => $number,
                'mode' => config('ai.mode'),
                'profile_snapshot' => $profile->only(['id', 'website_url', 'fields', 'confirmed_at']),
                'knowledge_record_ids' => $knowledge->pluck('id')->all(),
            ]);
            $round->ideas()->createMany($ideas);
        });

        return redirect()->to(route('workflow.index').'#round-'.$number)
            ->with('status', "Generated round {$number}: ".count($ideas).' ideas.');
    }
}
