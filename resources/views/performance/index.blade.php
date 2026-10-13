<x-layouts::app title="Performance & Insights">
    <div class="banner banner-synthetic" role="note">
        <strong>Synthetic Demo Data — Not Real Aquarium Park Analytics.</strong>
        Every record below is fictional, created for this proof of concept. No social account is connected.
    </div>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Step 4 — Load performance data</h2>
                <p class="muted">
                    Source: <code>{{ config('performance.synthetic_path') }}</code> (read-only; the app never writes to it).
                </p>
            </div>
            <form method="POST" action="{{ route('performance.load') }}">
                @csrf
                <button type="submit" class="btn btn-primary">{{ $dataset ? 'Reload synthetic dataset' : 'Load synthetic dataset' }}</button>
            </form>
        </div>

        @if ($dataset)
            <dl class="facts">
                <div><dt>Dataset</dt><dd>{{ $dataset->name }}</dd></div>
                <div><dt>Version</dt><dd>{{ $dataset->version }}</dd></div>
                <div><dt>Records</dt><dd class="num">{{ $records->count() }}</dd></div>
                <div><dt>Evidence type</dt><dd><x-provenance type="synthetic" /></dd></div>
                <div><dt>Loaded</dt><dd>{{ $dataset->loaded_at->toDisplay() }}</dd></div>
            </dl>
            @if ($dataset->metadata['warning'] ?? null)
                <p class="muted small">Dataset warning: {{ $dataset->metadata['warning'] }}</p>
            @endif
        @else
            <p class="empty">No performance data loaded yet. Load the synthetic dataset to see the 30 demo posts.</p>
        @endif
    </section>

    @if ($analysis)
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Evidence-graded comparisons</h2>
                    <p class="muted small">
                        Calculated by plain arithmetic, not AI, from the synthetic dataset {{ $dataset->version }}.
                        Each group is compared with the other valid posts in the same dimension.
                    </p>
                </div>
                <form method="POST" action="{{ route('knowledge.build') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ $knowledgeCount ? 'Regenerate knowledge records' : 'Save insights as knowledge records' }}</button>
                </form>
            </div>

            <div class="method">
                <div><strong>Formulas:</strong>
                    <code>{{ \App\Services\PerformanceAnalyzer::ER_FORMULA }}</code> ·
                    <code>{{ \App\Services\PerformanceAnalyzer::CTR_FORMULA }}</code> ·
                    <code>{{ \App\Services\PerformanceAnalyzer::CV_FORMULA }}</code>
                </div>
                <div><strong>Evidence ladder:</strong>
                    n &lt; {{ config('performance.min_sample') }} → Insufficient Evidence (no recommendation) ·
                    n {{ config('performance.min_sample') }}–{{ config('performance.medium_sample') - 1 }} → Low ·
                    n ≥ {{ config('performance.medium_sample') }} and CV ≤ {{ config('performance.medium_max_cv') }} → Medium ·
                    n ≥ {{ config('performance.medium_sample') }} and CV &gt; {{ config('performance.medium_max_cv') }} → Low ·
                    High is never assigned automatically.
                </div>
                <div><strong>Excluded records</strong> (reach ≤ 0 or a missing metric): {{ reset($analysis)['n_excluded'] }} of {{ $records->count() }}.</div>
                @if ($knowledgeCount)
                    <div><a href="{{ route('knowledge.index') }}">{{ $knowledgeCount }} knowledge records saved from this dataset →</a></div>
                @endif
            </div>

            @foreach ($analysis as $dim)
                <h3 class="dim-title">By {{ strtolower($dim['label']) }}</h3>
                <div class="table-wrap">
                    <table class="table analysis">
                        <thead>
                        <tr>
                            <th>Group</th>
                            <th>Mean ER (by reach)</th>
                            <th class="num">n</th>
                            <th class="num">Excl.</th>
                            <th class="num">CV</th>
                            <th class="num">Mean CTR</th>
                            <th class="num">vs other posts</th>
                            <th>Confidence</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($dim['groups'] as $g)
                            <tr @class(['row-insufficient' => $g['confidence'] === 'insufficient'])>
                                <td><strong>{{ $g['value'] }}</strong></td>
                                <td class="bar-cell">
                                    @if ($g['mean_er'] !== null)
                                        <div class="bar-wrap">
                                            <div class="bar" aria-hidden="true"><span style="width: {{ $dim['max_mean_er'] > 0 ? round($g['mean_er'] / $dim['max_mean_er'] * 100, 1) : 0 }}%"></span></div>
                                            <span class="num">{{ number_format($g['mean_er'], 2) }}%</span>
                                        </div>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="num">{{ $g['n_valid'] }}</td>
                                <td class="num">{{ $g['n_excluded'] }}</td>
                                <td class="num">{{ $g['cv'] === null ? 'N/A' : number_format($g['cv'], 2) }}</td>
                                <td class="num">{{ $g['mean_ctr'] === null ? 'N/A' : number_format($g['mean_ctr'], 2).'%' }}</td>
                                <td class="num">
                                    @if ($g['difference_pp'] === null)
                                        N/A
                                    @else
                                        {{ $g['difference_pp'] >= 0 ? '+' : '−' }}{{ number_format(abs($g['difference_pp']), 2) }} pp
                                        <span class="muted small">(vs {{ number_format($g['rest_mean_er'], 2) }}%, n={{ $g['rest_n'] }})</span>
                                    @endif
                                </td>
                                <td><x-confidence :level="$g['confidence']" :n="$g['n_valid']" :cv="$g['cv']" /></td>
                            </tr>
                            <tr class="row-detail">
                                <td colspan="8">
                                    @if ($g['observation'])
                                        <x-provenance type="simulated" source="synthetic dataset" />
                                        {{ $g['observation'] }}
                                        @if ($g['recommendation'])
                                            <span class="muted">Recommendation: {{ $g['recommendation'] }}</span>
                                        @endif
                                    @else
                                        <span class="muted">Descriptive metrics only. {{ $g['rationale'] }}</span>
                                    @endif
                                    <div class="muted small">Posts: {{ implode(', ', $g['post_ids']) ?: 'none' }}</div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="caption">Mean engagement rate by reach (%) per group; bars are relative to the highest group. Source: synthetic demo dataset, not real analytics.</p>
            @endforeach
        </section>
    @endif

    @if ($records->isNotEmpty())
        <section class="card">
            <h2>Posts ({{ $records->count() }})</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Post ID</th><th>Date</th><th>Platform</th><th>Format</th><th>Theme</th><th>Goal</th>
                        <th class="num">Impr.</th><th class="num">Reach</th><th class="num">Likes</th>
                        <th class="num">Comm.</th><th class="num">Shares</th><th class="num">Clicks</th><th class="num">Saves</th>
                        <th>Source</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($records as $r)
                        <tr>
                            <td><code>{{ $r->post_id }}</code></td>
                            <td>{{ $r->published_date?->format('Y-m-d') ?? 'N/A' }}</td>
                            <td>{{ $r->platform }}</td>
                            <td>{{ $r->content_format }}</td>
                            <td>{{ $r->post_theme }}</td>
                            <td>{{ $r->campaign_goal }}</td>
                            @foreach (\App\Models\PerformanceRecord::METRICS as $metric)
                                <td class="num">{{ $r->$metric === null ? 'N/A' : number_format($r->$metric) }}</td>
                            @endforeach
                            <td><x-provenance type="synthetic" :source="$r->data_source" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts::app>
