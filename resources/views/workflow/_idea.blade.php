{{-- One content idea. Expects $idea; $selectable adds a checkbox for the selection form. --}}
<article @class(['idea', 'idea-selected' => $idea->selected])>
    <div class="idea-head">
        @if ($selectable ?? false)
            <input type="checkbox" name="selected[]" value="{{ $idea->id }}" id="idea-{{ $idea->id }}" @checked($idea->selected) aria-label="Select {{ $idea->title }}">
        @endif
        <label for="idea-{{ $idea->id }}" class="idea-title">{{ $idea->title }}</label>
    </div>
    <div class="tags">
        <span class="tag">{{ $idea->platform }}</span>
        <span class="tag tag-format">{{ $idea->formatLabel() }}</span>
        <span class="tag">{{ $idea->campaign_goal }}</span>
        <x-provenance :type="$idea->provenance === 'live_ai' ? 'live' : 'fixture'" />
    </div>
    <p class="idea-hook">“{{ $idea->hook }}”</p>
    <dl class="idea-meta">
        <div><dt>Key message</dt><dd>{{ $idea->key_message }}</dd></div>
        <div><dt>Audience</dt><dd>{{ $idea->audience }}</dd></div>
        <div><dt>Objective</dt><dd>{{ $idea->objective }}</dd></div>
        <div><dt>CTA</dt><dd>{{ $idea->cta }}</dd></div>
        @if ($idea->isVideo())
            <div><dt>Video</dt><dd>{{ $idea->duration_s }}s · {{ $idea->aspect_ratio }}</dd></div>
        @endif
    </dl>

    @if ($idea->round->number === 2)
        <div @class(['why', 'why-exploratory' => ! $idea->knowledge_driven])>
            <div class="why-title">
                Why this was suggested
                @if ($idea->knowledge_driven)
                    <x-provenance type="simulated" source="synthetic data" />
                @else
                    <span class="prov prov-assumption">Not knowledge-driven</span>
                @endif
            </div>
            @if ($idea->knowledge_driven)
                <ul class="why-list">
                    @foreach ($idea->influencingRecords() as $record)
                        <li>
                            <a href="{{ route('knowledge.show', $record) }}"><code>{{ $record->key }}</code></a>
                            <x-confidence :level="$record->confidence" :n="$record->sample_size" :cv="$record->cv" />
                            <div class="small">
                                {{ $record->group_value }}: {{ number_format($record->metrics['mean_er'], 2) }}% mean ER,
                                {{ sprintf('%+.2f', $record->metrics['difference_pp']) }} pp vs the other {{ $record->metrics['comparison_n'] }} posts
                                ({{ number_format($record->metrics['comparison_mean_er'], 2) }}%).
                            </div>
                            <div class="muted small">Supporting posts: {{ implode(', ', $record->supporting_post_ids) }}</div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="small">{{ $idea->why }}</p>
            @endif
        </div>
    @endif
</article>
