<x-layouts::app title="Dashboard">
    <section class="card">
        <h2>Workflow</h2>
        <p class="muted">The guided five-step dashboard is completed in a later build step. Available now:</p>
        <ul class="plain-list">
            <li>
                <strong>Step 4 — Performance &amp; Insights:</strong>
                @if ($dataset)
                    synthetic dataset loaded ({{ $dataset->records()->count() }} records).
                @else
                    no dataset loaded yet.
                @endif
                <a href="{{ route('performance.index') }}">Open Performance &amp; Insights</a>
            </li>
            <li>
                <strong>Knowledge Base:</strong>
                {{ \App\Models\KnowledgeRecord::count() }} records.
                <a href="{{ route('knowledge.index') }}">Open Knowledge Base</a>
            </li>
        </ul>
    </section>
</x-layouts::app>
