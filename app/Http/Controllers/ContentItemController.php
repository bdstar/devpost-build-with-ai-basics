<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\ContentIdea;
use App\Models\ContentItem;
use App\Models\StatusHistory;
use App\Services\Ai\AiException;
use App\Services\Ai\AiProvider;
use App\Services\Ai\ContentDraft;
use App\Services\ContentWorkflow;
use App\Services\WorkflowException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContentItemController extends Controller
{
    public function index(): View
    {
        return view('content.index', [
            'items' => ContentItem::with('idea.round')->orderBy('id')->get(),
            'pendingIdeas' => $this->selectedIdeasWithoutContent()->count(),
        ]);
    }

    public function show(ContentItem $item, ContentWorkflow $workflow): View
    {
        return view('content.show', [
            'item' => $item->load('idea.round', 'histories'),
            'workflow' => $workflow,
        ]);
    }

    public function generate(): RedirectResponse
    {
        $profile = BusinessProfile::current();
        if (! $profile?->isConfirmed()) {
            return redirect()->route('content.index')->with('error', 'Confirm the business profile before generating content.');
        }
        $ideas = $this->selectedIdeasWithoutContent();
        if ($ideas->isEmpty()) {
            return redirect()->route('content.index')->with('error', 'Select ideas in the Content Workflow first. Every selected idea already has content.');
        }

        try {
            $provider = app(AiProvider::class);
            $drafts = $ideas->mapWithKeys(fn (ContentIdea $idea) => [$idea->id => $provider->content($idea, $profile)]);
        } catch (AiException $e) {
            return redirect()->route('content.index')->with('error', 'Content generation failed: '.$e->getMessage().' Existing work was kept.');
        }

        DB::transaction(function () use ($drafts) {
            foreach ($drafts as $ideaId => $draft) {
                $item = ContentItem::create(['idea_id' => $ideaId, 'status' => ContentItem::DRAFT] + $this->attributes($draft));
                StatusHistory::create([
                    'content_item_id' => $item->id, 'from_status' => null, 'to_status' => ContentItem::DRAFT,
                    'note' => 'Generated ('.($draft->provenance === 'live_ai' ? 'Live AI' : 'Demo fixture').').', 'actor' => 'system',
                ]);
            }
        });

        return redirect()->route('content.index')->with('status', 'Generated content for '.$drafts->count().' idea(s) as drafts.');
    }

    public function update(Request $request, ContentItem $item, ContentWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'caption' => ['required', 'string', 'max:5000'],
            'hashtags' => ['nullable', 'string', 'max:1000'],
            'cta' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->attempt($item, fn () => $workflow->edit($item, $data), 'Saved.');
    }

    public function regenerate(ContentItem $item, ContentWorkflow $workflow): RedirectResponse
    {
        if ($item->status !== ContentItem::DRAFT) {
            return back()->with('error', 'Only drafts can be regenerated.');
        }
        try {
            $draft = app(AiProvider::class)->content($item->idea, BusinessProfile::current());
        } catch (AiException $e) {
            return back()->with('error', 'Regeneration failed: '.$e->getMessage().' The current content was kept.');
        }

        return $this->attempt($item, fn () => $workflow->edit($item, $this->attributes($draft)), 'Regenerated.');
    }

    public function transition(Request $request, ContentItem $item, string $action, ContentWorkflow $workflow): RedirectResponse
    {
        $messages = [
            'submit' => 'Submitted for approval.',
            'approve' => 'Approved.',
            'request-changes' => 'Changes requested; the item is back in Draft with your feedback.',
            'reject' => 'Rejected.',
            'reopen' => 'Reopened as a draft.',
        ];
        abort_unless(isset($messages[$action]), 404);

        return $this->attempt($item, fn () => match ($action) {
            'submit' => $workflow->submit($item),
            'approve' => $workflow->approve($item),
            'request-changes' => $workflow->requestChanges($item, $request->input('feedback')),
            'reject' => $workflow->reject($item, $request->input('reason')),
            'reopen' => $workflow->reopen($item),
        }, $messages[$action]);
    }

    protected function attempt(ContentItem $item, callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (WorkflowException $e) {
            return redirect()->route('content.show', $item)->with('error', $e->getMessage());
        }

        return redirect()->route('content.show', $item)->with('status', $success);
    }

    private function attributes(ContentDraft $draft): array
    {
        return [
            'caption' => $draft->caption,
            'hashtags' => $draft->hashtags,
            'cta' => $draft->cta,
            'alt_text' => $draft->altText,
            'image_path' => $draft->imagePath,
            'image_provenance' => $draft->imageProvenance,
            'video_brief' => $draft->videoBrief,
            'provenance' => $draft->provenance.($draft->model ? ':'.$draft->model : ''),
        ];
    }

    private function selectedIdeasWithoutContent()
    {
        return ContentIdea::where('selected', true)->whereDoesntHave('items')->orderBy('id')->get();
    }
}
