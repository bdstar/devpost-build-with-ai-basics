<x-layouts::app title="Knowledge Base">
    <div class="banner banner-synthetic" role="note">
        <strong>Simulated Insights.</strong>
        These records are derived from synthetic demo data. They describe the sample, not real Aquarium Park audience behaviour.
    </div>

    @if ($records->isEmpty())
        <section class="card">
            <p class="empty">
                No knowledge records yet. They appear after you load the dataset and save insights from
                <a href="{{ route('performance.index') }}">Performance &amp; Insights</a>.
                Only groups with at least {{ config('performance.min_sample') }} comparable posts become knowledge records.
            </p>
        </section>
    @else
        @foreach ($records as $category => $items)
            <section class="card">
                <h2>{{ $category }}</h2>
                <ul class="kr-list">
                    @foreach ($items as $record)
                        <li class="kr-item">
                            <div class="kr-head">
                                <a href="{{ route('knowledge.show', $record) }}" class="kr-key"><code>{{ $record->key }}</code></a>
                                <x-confidence :level="$record->confidence" :n="$record->sample_size" :cv="$record->cv" />
                                <x-provenance type="simulated" :source="$record->evidence_type.' · '.$record->source_dataset" />
                            </div>
                            <p>{{ $record->observation }}</p>
                            @if ($record->recommendation)
                                <p class="muted">Recommendation: {{ $record->recommendation }}</p>
                            @endif
                            <p class="muted small">Supporting posts: {{ implode(', ', $record->supporting_post_ids) }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</x-layouts::app>
