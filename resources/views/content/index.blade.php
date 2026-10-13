<x-layouts::app title="Content Workflow">
    <x-step-indicator :current="3" />
    <p><a href="{{ route('workflow.index') }}">← Ideas (Steps 2 and 5)</a></p>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Step 3 — Generate, review and approve</h2>
                <p class="muted small">Each selected idea becomes a content item in Draft. Nothing is published to any platform.</p>
            </div>
            @if ($pendingIdeas > 0)
                <form method="POST" action="{{ route('content.generate') }}" data-loading="Generating content…">
                    @csrf
                    <button type="submit" class="btn btn-primary">Generate content for {{ $pendingIdeas }} selected idea(s)</button>
                </form>
            @endif
        </div>

        @if ($items->isEmpty())
            <p class="empty">No content yet. Select ideas in the <a href="{{ route('workflow.index') }}">Content Workflow</a>, then generate content here.</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Idea</th><th>Round</th><th>Platform</th><th>Format</th><th>Status</th><th>Scheduled (Asia/Dhaka)</th></tr></thead>
                    <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><a href="{{ route('content.show', $item) }}">{{ $item->idea->title }}</a></td>
                            <td>{{ $item->idea->round->number }}</td>
                            <td>{{ $item->idea->platform }}</td>
                            <td>{{ $item->idea->formatLabel() }}</td>
                            <td><x-status-pill :item="$item" /></td>
                            <td>{{ $item->scheduled_at?->toDisplay() ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts::app>
