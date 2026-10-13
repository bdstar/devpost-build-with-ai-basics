<x-layouts::app title="Dashboard">
    @php
        $next = $progress->next();
        $done = $progress->completed();
        $current = $progress->current();
    @endphp

    <section class="card continue">
        <div>
            <h2>{{ $current ? "Current step: Step {$current['number']} — {$current['title']}" : 'All five steps complete' }}</h2>
            <p class="muted small">{{ $done }} of 5 steps complete</p>
            <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="5" aria-valuenow="{{ $done }}"><span style="width: {{ $done * 20 }}%"></span></div>
        </div>
        <a href="{{ $next['url'] }}" class="btn btn-primary">Continue: {{ $next['label'] }} →</a>
    </section>

    <x-step-indicator :current="$current['number'] ?? null" />

    <div class="dash-grid">
        <section class="card">
            <h3>Business profile</h3>
            @if ($profile)
                <p><strong>{{ $profile->value('business_name') ?: 'Unnamed business' }}</strong></p>
                <p class="small">{{ $profile->isConfirmed() ? 'Confirmed '.$profile->confirmed_at->toDisplay() : 'Draft — not confirmed yet' }}</p>
                <p class="small muted">{{ $profile->website_url }}</p>
            @else
                <p class="empty">Not started. Build it from the public website or enter it manually.</p>
            @endif
            <a href="{{ route('profile.show') }}">Open Business Profile</a>
        </section>

        <section class="card">
            <h3>Round one and approval</h3>
            @if ($roundOne)
                <p class="small">{{ $roundOne->ideas()->count() }} ideas generated {{ $roundOne->created_at->toDisplay() }}; {{ $roundOne->ideas()->where('selected', true)->count() }} selected.</p>
                @if ($statusCounts->isEmpty())
                    <p class="small muted">No content generated yet.</p>
                @else
                    <ul class="plain-list small">
                        @foreach (\App\Models\ContentItem::STATUS_LABELS as $status => $label)
                            @if ($statusCounts[$status] ?? 0)
                                <li>{{ $label }}: <strong>{{ $statusCounts[$status] }}</strong></li>
                            @endif
                        @endforeach
                    </ul>
                    @if ($readyCount)
                        <p class="small"><span class="pill pill-ready">Ready to Publish — Manual Action Required</span> {{ $readyCount }} item(s)</p>
                    @endif
                @endif
            @else
                <p class="empty">No ideas yet.</p>
            @endif
            <a href="{{ route('content.index') }}">Open content</a>
        </section>

        <section class="card">
            <h3>Performance data source</h3>
            @if ($dataset)
                <p><x-provenance type="synthetic" /></p>
                <p class="small">{{ $dataset->records()->count() }} synthetic demo posts, loaded {{ $dataset->loaded_at->toDisplay() }}. Not real Aquarium Park analytics; no social account is connected.</p>
            @else
                <p class="empty">No performance data loaded. Step 4 loads a synthetic demo dataset (clearly labelled, never presented as real analytics).</p>
            @endif
            <a href="{{ route('performance.index') }}">Open Performance &amp; Insights</a>
        </section>

        <section class="card">
            <h3>Knowledge from round one</h3>
            @if ($insights->isEmpty())
                <p class="empty">No knowledge records yet. They appear after the dataset is analysed and insights are saved.</p>
            @else
                <p class="small"><x-provenance type="insight" /> {{ $insights->count() }} records from synthetic data.</p>
                <ul class="plain-list small">
                    @foreach ($insights->filter(fn ($r) => ($r->metrics['direction'] ?? null) === 'above')->take(4) as $record)
                        <li><a href="{{ route('knowledge.show', $record) }}"><code>{{ $record->key }}</code></a>
                            +{{ number_format($record->metrics['difference_pp'], 2) }} pp · <x-confidence :level="$record->confidence" :n="$record->sample_size" :cv="$record->cv" /></li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('knowledge.index') }}">Open Knowledge Base</a>
        </section>

        <section class="card">
            <h3>Round two</h3>
            @if ($roundTwo)
                <p class="small">{{ $roundTwo->ideas()->count() }} ideas, {{ $roundTwo->ideas()->where('knowledge_driven', true)->count() }} knowledge-driven, generated {{ $roundTwo->created_at->toDisplay() }}.</p>
                <a href="{{ route('workflow.index') }}#comparison">See the round-one vs round-two comparison</a>
            @else
                <p class="empty">Not generated yet. Round two uses the knowledge records as explicit inputs.</p>
                <a href="{{ route('workflow.index') }}#round-2">Open round two</a>
            @endif
        </section>
    </div>

    <section class="card card-danger" id="reset">
        <h3>Reset all project data</h3>
        <p class="small">Permanently deletes the Business Profile, every idea round, all content items and their history, the loaded performance dataset, all Knowledge Base records and any AI-generated images. This cannot be undone.</p>
        <p class="small muted">Your login is kept, and the synthetic dataset source files are not touched, so Step 4 can load them again.</p>
        <form method="POST" action="{{ route('data.reset') }}" class="inline-form"
              onsubmit="return confirm('Delete all project data? This cannot be undone.');">
            @csrf
            <input name="confirmation" type="text" autocomplete="off" required
                   placeholder="Type {{ \App\Http\Controllers\ResetController::CONFIRMATION }} to confirm" aria-label="Type RESET to confirm">
            <button type="submit" class="btn btn-danger">Reset all data</button>
        </form>
    </section>
</x-layouts::app>
