<x-layouts::app title="Content Workflow">
    <x-step-indicator :current="$roundTwo ? 5 : 2" />

    {{-- Step 2 — Round-one ideas --}}
    <section class="card" id="round-1">
        <div class="card-head">
            <div>
                <h2>Step 2 — Round-one content ideas</h2>
                <p class="muted small">Generated from the confirmed business profile only. No performance knowledge is used in round one.</p>
            </div>
            @if (! $roundOne && $profile?->isConfirmed())
                <form method="POST" action="{{ route('workflow.round-one') }}" data-loading="Generating ideas…">
                    @csrf
                    <button type="submit" class="btn btn-primary">Generate round-one ideas</button>
                </form>
            @endif
        </div>

        @if (! $profile?->isConfirmed())
            <p class="locked">Locked: confirm the business profile first. <a href="{{ route('profile.show') }}">Go to Step 1 — Business Profile</a></p>
        @elseif (! $roundOne)
            <p class="empty">No ideas yet. Generate round one to get about six ideas across Facebook and Instagram, in text, image, carousel and video (Reel) formats.</p>
        @else
            <form method="GET" action="{{ route('workflow.index') }}" class="filters">
                @foreach (['platform' => 'Platform', 'campaign_goal' => 'Goal', 'format' => 'Format'] as $field => $label)
                    <label>{{ $label }}
                        <select name="{{ $field }}">
                            <option value="">All</option>
                            @foreach ($filterOptions[$field] as $option)
                                <option value="{{ $option }}" @selected(($filters[$field] ?? '') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
                <button type="submit" class="btn">Filter</button>
                @if ($filters)
                    <a href="{{ route('workflow.index') }}" class="btn btn-link">Clear</a>
                @endif
            </form>

            <form method="POST" action="{{ route('workflow.select', $roundOne) }}">
                @csrf
                @forelse ($roundOneIdeas->groupBy('platform') as $platform => $ideas)
                    <h3 class="dim-title">{{ $platform }}</h3>
                    <div class="idea-grid">
                        @foreach ($ideas as $idea)
                            @include('workflow._idea', ['idea' => $idea, 'selectable' => true])
                        @endforeach
                    </div>
                @empty
                    <p class="empty">No ideas match these filters.</p>
                @endforelse
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Save selection</button>
                    <a href="{{ route('content.index') }}" class="btn">Step 3 — Generate and review content →</a>
                </div>
            </form>
        @endif
    </section>

    {{-- Step 5 — Knowledge-driven round two --}}
    <section class="card" id="round-2">
        <div class="card-head">
            <div>
                <h2>Step 5 — Knowledge-driven round two</h2>
                <p class="muted small">Uses the confirmed profile plus the {{ $knowledgeCount }} knowledge records as explicit inputs.</p>
            </div>
            @if ($roundOne && $knowledgeCount > 0 && $profile?->isConfirmed())
                <form method="POST" action="{{ route('workflow.round-two') }}" data-loading="Generating round two…">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ $roundTwo ? 'Regenerate round two' : 'Generate round two' }}</button>
                </form>
            @endif
        </div>

        @if (! $roundOne)
            <p class="locked">Locked: generate round one first, so round two can be compared with it.</p>
        @elseif ($knowledgeCount === 0)
            <p class="locked">Locked: round two needs knowledge records. <a href="{{ route('performance.index') }}">Load the dataset and save insights in Performance &amp; Insights</a>.</p>
        @elseif (! $roundTwo)
            <p class="empty">Round two has not been generated yet.</p>
        @else
            <div class="banner banner-synthetic" role="note">
                <strong>Simulated Insights.</strong> Round two is driven by knowledge records derived from synthetic demo data, not real Aquarium Park analytics.
            </div>
            <form method="POST" action="{{ route('workflow.select', $roundTwo) }}">
                @csrf
                <div class="idea-grid">
                    @foreach ($roundTwoIdeas as $idea)
                        @include('workflow._idea', ['idea' => $idea, 'selectable' => true])
                    @endforeach
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Save selection</button>
                </div>
            </form>
        @endif
    </section>

    @if ($comparison)
        <section class="card" id="comparison">
            <h2>Round one vs round two</h2>
            <p class="disclaimer">{{ $comparison['disclaimer'] }}</p>
            <p class="small">
                {{ $comparison['knowledge_driven'] }} of {{ $comparison['counts']['round_two'] }} round-two ideas are knowledge-driven.
                Differences in: {{ $comparison['differing'] ? collect($comparison['differing'])->map(fn ($d) => strtolower($comparison['dimensions'][$d]['label']))->implode(', ') : 'none' }}{{ $comparison['cta']['differs'] ? ', CTA' : '' }}.
            </p>
            <div class="compare-grid">
                @foreach ($comparison['dimensions'] as $dim)
                    <table class="table compare">
                        <thead><tr><th>{{ $dim['label'] }}</th><th class="num">Round 1</th><th class="num">Round 2</th><th class="num">Change</th></tr></thead>
                        <tbody>
                        @foreach ($dim['rows'] as $row)
                            <tr>
                                <td>{{ $row['value'] }}</td>
                                <td class="num">{{ $row['round_one'] }}</td>
                                <td class="num">{{ $row['round_two'] }}</td>
                                <td @class(['num', 'delta-up' => $row['delta'] > 0, 'delta-down' => $row['delta'] < 0])>{{ $row['delta'] > 0 ? '+' : '' }}{{ $row['delta'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
            <div class="compare-grid">
                <div>
                    <h3>Calls to action</h3>
                    <p class="small"><strong>Round 1:</strong> {{ implode(' · ', $comparison['cta']['round_one']) }}</p>
                    <p class="small"><strong>Round 2:</strong> {{ implode(' · ', $comparison['cta']['round_two']) }}</p>
                </div>
                <div>
                    <h3>Topics</h3>
                    <p class="small"><strong>New in round 2:</strong> {{ implode(' · ', $comparison['topics']['added']) ?: 'none' }}</p>
                    <p class="small"><strong>Not in round 2:</strong> {{ implode(' · ', $comparison['topics']['dropped']) ?: 'none' }}</p>
                </div>
            </div>
        </section>
    @endif
</x-layouts::app>
