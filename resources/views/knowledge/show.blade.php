<x-layouts::app title="Knowledge Record">
    <p><a href="{{ route('knowledge.index') }}">← Knowledge Base</a></p>

    <section class="card">
        <div class="kr-head">
            <h2><code>{{ $record->key }}</code></h2>
            <x-confidence :level="$record->confidence" :n="$record->sample_size" :cv="$record->cv" />
            <x-provenance type="simulated" :source="$record->evidence_type.' · '.$record->source_dataset" />
        </div>

        <p>{{ $record->observation }}</p>
        @if ($record->recommendation)
            <p><strong>Recommendation:</strong> {{ $record->recommendation }}</p>
        @endif

        <dl class="facts facts-grid">
            <div><dt>Category</dt><dd>{{ $record->category }}</dd></div>
            <div><dt>Group</dt><dd>{{ $record->group_value }}</dd></div>
            <div><dt>Sample size (n)</dt><dd class="num">{{ $record->sample_size }}</dd></div>
            <div><dt>Excluded records</dt><dd class="num">{{ $record->excluded_count }}</dd></div>
            <div><dt>Mean ER</dt><dd class="num">{{ number_format($record->metrics['mean_er'], 2) }}%</dd></div>
            <div><dt>Std dev of ER</dt><dd class="num">{{ number_format($record->metrics['sd_er'], 2) }}</dd></div>
            <div><dt>CV</dt><dd class="num">{{ $record->cv === null ? 'N/A' : number_format($record->cv, 2) }}</dd></div>
            <div><dt>Mean CTR</dt><dd class="num">{{ $record->metrics['mean_ctr'] === null ? 'N/A' : number_format($record->metrics['mean_ctr'], 2).'%' }}</dd></div>
            <div><dt>Comparison mean ER</dt><dd class="num">{{ $record->metrics['comparison_mean_er'] === null ? 'N/A' : number_format($record->metrics['comparison_mean_er'], 2).'% (n='.$record->metrics['comparison_n'].')' }}</dd></div>
            <div><dt>Evidence type</dt><dd>{{ $record->evidence_type }}</dd></div>
            <div><dt>Source dataset</dt><dd>{{ $record->source_dataset }} v{{ $record->dataset_version }}</dd></div>
            <div><dt>Analysed</dt><dd>{{ $record->analysed_at->toDisplay() }}</dd></div>
        </dl>

        <p class="small"><strong>Calculation:</strong> <code>{{ $record->formula }}</code></p>
        <p class="small"><strong>Confidence rationale:</strong> {{ $record->rationale }}</p>
    </section>

    <section class="card">
        <h2>Supporting posts ({{ count($record->supporting_post_ids) }})</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Post ID</th><th>Date</th><th>Platform</th><th>Format</th><th>Goal</th><th>Theme</th>
                    <th class="num">Reach</th><th class="num">Engagements</th><th class="num">ER</th><th>Source</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($posts as $p)
                    @php $eng = $p->likes + $p->comments + $p->shares; @endphp
                    <tr>
                        <td><code>{{ $p->post_id }}</code></td>
                        <td>{{ $p->published_date?->format('Y-m-d') }}</td>
                        <td>{{ $p->platform }}</td>
                        <td>{{ $p->content_format }}</td>
                        <td>{{ $p->campaign_goal }}</td>
                        <td>{{ $p->post_theme }}</td>
                        <td class="num">{{ number_format($p->reach) }}</td>
                        <td class="num">{{ number_format($eng) }}</td>
                        <td class="num">{{ $p->reach > 0 ? number_format($eng / $p->reach * 100, 2).'%' : 'N/A' }}</td>
                        <td><x-provenance type="synthetic" :source="$p->data_source" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($posts->count() !== count($record->supporting_post_ids))
            <p class="field-error">Some supporting posts are no longer in the loaded dataset.</p>
        @endif
    </section>
</x-layouts::app>
