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
