<x-layouts::app title="Content Item">
    @php
        $idea = $item->idea;
        $editable = in_array($item->status, ['draft', 'approved', 'scheduled'], true);
    @endphp
    <x-step-indicator :current="3" />
    <p><a href="{{ route('content.index') }}">← All content</a></p>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>{{ $idea->title }}</h2>
                <div class="tags">
                    <span class="tag">Round {{ $idea->round->number }}</span>
                    <span class="tag">{{ $idea->platform }}</span>
                    <span class="tag tag-format">{{ $idea->formatLabel() }}</span>
                    <span class="tag">{{ $idea->campaign_goal }}</span>
                    <x-provenance :type="str_starts_with($item->provenance, 'live_ai') ? 'live' : 'fixture'" />
                </div>
            </div>
            <x-status-pill :item="$item" />
        </div>

        @if ($item->status === 'draft' && $item->feedback)
            <div class="flash flash-bad"><strong>Changes requested:</strong> {{ $item->feedback }}</div>
        @endif
        @if ($item->status === 'rejected')
            <div class="flash flash-bad"><strong>Rejected:</strong> {{ $item->rejection_reason }}</div>
        @endif

        <div class="content-layout">
            <div>
                <h3>Caption</h3>
                <p class="caption-text">{!! nl2br(e($item->caption)) !!}</p>
                <p class="small"><strong>Hashtags:</strong> {{ $item->hashtags }}</p>
                <p class="small"><strong>CTA:</strong> {{ $item->cta }}</p>
                @if ($item->alt_text)
                    <p class="small"><strong>Alt text:</strong> {{ $item->alt_text }}</p>
                @endif
            </div>

            <div>
                @if ($item->video_brief)
                    <div class="video-brief">
                        <div class="video-label">Video Not Generated — Planning Preview Only</div>
                        <p class="small muted">Production brief · {{ $item->video_brief['duration_s'] }}s · {{ $item->video_brief['aspect_ratio'] }}</p>
                        <h4>Script</h4>
                        <ol class="small">
                            @foreach ($item->video_brief['script'] as $scene)
                                <li><strong>{{ $scene['label'] }}:</strong> {{ $scene['direction'] }}</li>
                            @endforeach
                        </ol>
                        <h4>Shot list</h4>
                        <ul class="small">
                            @foreach ($item->video_brief['shot_list'] as $shot)
                                <li>{{ $shot }}</li>
                            @endforeach
                        </ul>
                        <h4>On-screen text</h4>
                        <ul class="small">
                            @foreach ($item->video_brief['on_screen_text'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                        <p class="small muted">{{ $item->video_brief['notes'] }}</p>
                    </div>
                @elseif ($item->image_path)
                    <figure class="post-image">
                        <img src="{{ $item->image_path }}" alt="{{ $item->alt_text }}">
                        <figcaption>
                            @if ($item->image_provenance === 'demo_fixture')
                                Demo fixture image — not generated in this session
                            @else
                                Generated image · {{ $item->image_provenance }}
                            @endif
                        </figcaption>
                    </figure>
                @else
                    <p class="muted small">Text post: no image.</p>
                @endif
            </div>
        </div>
    </section>

    <section class="card">
        <h3>Review actions</h3>
        <div class="review-actions">
            @if ($workflow->can($item, 'submit'))
                <form method="POST" action="{{ route('content.transition', [$item, 'submit']) }}">@csrf
                    <button class="btn btn-primary">Submit for approval</button>
                </form>
                <form method="POST" action="{{ route('content.regenerate', $item) }}" data-loading="Regenerating…">@csrf
                    <button class="btn">Regenerate</button>
                </form>
            @endif
            @if ($workflow->can($item, 'approve'))
                <form method="POST" action="{{ route('content.transition', [$item, 'approve']) }}">@csrf
                    <button class="btn btn-primary">Approve</button>
                </form>
                <form method="POST" action="{{ route('content.transition', [$item, 'request-changes']) }}" class="inline-form">@csrf
                    <input name="feedback" type="text" placeholder="What should change? (required)" aria-label="Feedback">
                    <button class="btn">Request changes</button>
                </form>
                <form method="POST" action="{{ route('content.transition', [$item, 'reject']) }}" class="inline-form">@csrf
                    <input name="reason" type="text" placeholder="Reason for rejecting (required)" aria-label="Rejection reason">
                    <button class="btn btn-danger">Reject</button>
                </form>
            @endif
            @if ($workflow->can($item, 'reopen'))
                <form method="POST" action="{{ route('content.transition', [$item, 'reopen']) }}">@csrf
                    <button class="btn">Reopen as draft</button>
                </form>
            @endif
            @if (in_array($item->status, ['draft', 'pending_approval', 'rejected'], true))
                <span class="muted small">Scheduling unlocks once this item is approved.</span>
            @endif
        </div>

        @if ($workflow->can($item, 'schedule'))
            @php $localNow = now()->setTimezone(config('app.display_timezone')); @endphp
            <div class="publish-panel {{ $item->isReadyToPublish() ? 'is-ready' : '' }}">
                @if ($item->status === 'scheduled')
                    <p><strong>Scheduled for {{ $item->scheduled_at->toDisplay() }}</strong> on {{ $item->idea->platform }}.</p>
                    @if ($item->isReadyToPublish())
                        <p><strong>Ready to Publish — Manual Action Required.</strong>
                            Automatic publishing is disabled: no verified account connection. Post it yourself on {{ $item->idea->platform }}, then mark it as published, or record a labelled simulation for the demo.</p>
                    @else
                        <p class="muted small">Nothing will be posted automatically. When this time passes, the item will show "Ready to Publish — Manual Action Required".</p>
                    @endif
                @endif

                <form method="POST" action="{{ route('content.transition', [$item, 'schedule']) }}" class="schedule-form">@csrf
                    <label>Date (Asia/Dhaka)
                        <input type="date" name="date" required min="{{ $localNow->format('Y-m-d') }}"
                               value="{{ $item->scheduled_at?->copy()->setTimezone(config('app.display_timezone'))->format('Y-m-d') }}">
                    </label>
                    <label>Time (Asia/Dhaka)
                        <input type="time" name="time" required
                               value="{{ $item->scheduled_at?->copy()->setTimezone(config('app.display_timezone'))->format('H:i') }}">
                    </label>
                    <button class="btn btn-primary">{{ $item->status === 'scheduled' ? 'Reschedule' : 'Schedule' }}</button>
                </form>

                @if ($item->status === 'scheduled')
                    <div class="review-actions" style="margin-top: 12px">
                        <form method="POST" action="{{ route('content.transition', [$item, 'unschedule']) }}">@csrf
                            <button class="btn">Unschedule</button>
                        </form>
                        <form method="POST" action="{{ route('content.transition', [$item, 'simulate-publish']) }}"
                              data-confirm="Record a simulated publication? Nothing is sent to any platform; it is labelled as simulated.">@csrf
                            <button class="btn">Simulate Publishing (demo)</button>
                        </form>
                    </div>
                    <form method="POST" action="{{ route('content.transition', [$item, 'mark-published']) }}">@csrf
                        <label class="confirm-check">
                            <input type="checkbox" name="confirmed" value="1" required>
                            I confirm this post was actually published on {{ $item->idea->platform }}.
                        </label>
                        <button class="btn">Mark as Manually Published</button>
                    </form>
                @endif
            </div>
        @endif

        @if ($item->status === 'published')
            <div class="publish-panel">
                <p><strong>Published {{ $item->published_at->toDisplay() }}</strong> —
                    @if ($item->publish_method === 'simulated')
                        Simulated — not sent to any platform.
                    @else
                        manually confirmed by the user (not verified by the platform).
                    @endif
                </p>
            </div>
        @endif
    </section>

    @if ($editable)
        <section class="card">
            <details @if ($errors->any()) open @endif>
                <summary><strong>Edit content</strong>
                    @if ($item->status !== 'draft')
                        <span class="muted small">— saving returns this {{ strtolower($item->statusLabel()) }} item to Draft for review again</span>
                    @endif
                </summary>
                <form method="POST" action="{{ route('content.update', $item) }}" class="form">
                    @csrf @method('PUT')
                    <label for="caption">Caption</label>
                    <textarea id="caption" name="caption" rows="5" required>{{ old('caption', $item->caption) }}</textarea>
                    @error('caption') <p class="field-error">{{ $message }}</p> @enderror
                    <label for="hashtags">Hashtags</label>
                    <input id="hashtags" name="hashtags" type="text" value="{{ old('hashtags', $item->hashtags) }}">
                    <label for="cta">CTA</label>
                    <input id="cta" name="cta" type="text" value="{{ old('cta', $item->cta) }}">
                    @if ($item->image_path)
                        <label for="alt_text">Alt text</label>
                        <input id="alt_text" name="alt_text" type="text" value="{{ old('alt_text', $item->alt_text) }}">
                    @endif
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </details>
        </section>
    @endif

    <section class="card">
        <h3>Status history</h3>
        <table class="table">
            <thead><tr><th>When (Asia/Dhaka)</th><th>From</th><th>To</th><th>Note</th><th>By</th></tr></thead>
            <tbody>
            @foreach ($item->histories as $h)
                <tr>
                    <td>{{ $h->created_at->toDisplay('j M Y, g:i:s A') }}</td>
                    <td>{{ $h->from_status ? \App\Models\ContentItem::STATUS_LABELS[$h->from_status] : '—' }}</td>
                    <td>{{ \App\Models\ContentItem::STATUS_LABELS[$h->to_status] }}</td>
                    <td>{{ $h->note }}</td>
                    <td>{{ $h->actor }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</x-layouts::app>
