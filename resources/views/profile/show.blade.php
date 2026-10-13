<x-layouts::app title="Business Profile">
    @php
        $socialValues = old('social_urls', $profile->social_urls ?? []);
        $urlValue = old('website_url', $profile->website_url ?? 'https://aquariumpark.com.bd/');
    @endphp

    @if ($showAnalyse)
        <section class="card">
            <h2>Step 1 — Read the business website</h2>
            <p class="muted">
                The app reads the public homepage and up to 8 linked pages on the same site (about, visit, contact, attractions).
                Facts found on those pages are labelled <x-provenance type="verified" />; everything else is labelled
                <x-provenance type="assumption" />.
            </p>
            <form method="POST" action="{{ route('profile.analyse') }}" class="form" data-loading="Reading the website…">
                @csrf
                <label for="website_url">Website URL</label>
                <input id="website_url" name="website_url" type="text" value="{{ $urlValue }}" required placeholder="https://example.com/">
                @error('website_url') <p class="field-error">{{ $message }}</p> @enderror

                <details>
                    <summary>Social profile URLs (optional — recorded, not analysed in this proof of concept)</summary>
                    <div class="form-grid">
                        @foreach (\App\Models\BusinessProfile::SOCIAL_PLATFORMS as $key => $label)
                            <div>
                                <label for="social_{{ $key }}">{{ $label }}</label>
                                <input id="social_{{ $key }}" name="social_urls[{{ $key }}]" type="text" value="{{ $socialValues[$key] ?? '' }}">
                                @error("social_urls.{$key}") <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </details>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Read website and build profile</button>
                    <a href="{{ route('profile.show', ['manual' => 1]) }}" class="btn">Enter profile manually</a>
                    @if ($profile)
                        <a href="{{ route('profile.show') }}" class="btn btn-link">Cancel</a>
                    @endif
                </div>
            </form>
        </section>
    @endif

    @if ($showManual)
        <section class="card" id="manual">
            <h2>Enter the profile manually</h2>
            <p class="muted">Every field you enter here is labelled <x-provenance type="manual" />.</p>
            <form method="POST" action="{{ route('profile.manual') }}" class="form">
                @csrf
                <label for="manual_website_url">Website URL</label>
                <input id="manual_website_url" name="website_url" type="text" value="{{ $urlValue }}" required>
                @foreach (\App\Models\BusinessProfile::FIELDS as $key => $meta)
                    <label for="manual_{{ $key }}">{{ $meta['label'] }}@if ($meta['list']) <span class="muted small">(one per line)</span>@endif</label>
                    <textarea id="manual_{{ $key }}" name="fields[{{ $key }}]" rows="{{ $meta['list'] ? 3 : 2 }}">{{ old("fields.{$key}") }}</textarea>
                @endforeach
                <button type="submit" class="btn btn-primary">Save manual profile</button>
            </form>
        </section>
    @endif

    @if ($profile)
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>{{ $profile->value('business_name') ?: 'Business profile' }}</h2>
                    <p class="muted small">
                        Website: <a href="{{ $profile->website_url }}" target="_blank" rel="noopener noreferrer">{{ $profile->website_url }}</a>
                        @if ($profile->sources['retrieved_at'] ?? null)
                            · read {{ \Illuminate\Support\Carbon::parse($profile->sources['retrieved_at'])->toDisplay() }}
                        @endif
                    </p>
                </div>
                <div class="head-actions">
                    @if ($profile->isConfirmed())
                        <span class="badge badge-ok">Confirmed {{ $profile->confirmed_at->toDisplay('j M, g:i A') }}</span>
                    @else
                        <span class="badge badge-draft">Draft — not confirmed</span>
                        <form method="POST" action="{{ route('profile.confirm') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Confirm profile</button>
                        </form>
                    @endif
                </div>
            </div>

            <dl class="profile-fields">
                @foreach (\App\Models\BusinessProfile::FIELDS as $key => $meta)
                    @php $field = $profile->fields[$key] ?? null; @endphp
                    @continue(! $field)
                    <div class="profile-field prov-row-{{ $field['provenance'] }}">
                        <dt>
                            {{ $meta['label'] }}
                            @switch($field['provenance'])
                                @case('verified')
                                    <x-provenance type="verified" :href="$field['source_url']" :source="parse_url($field['source_url'], PHP_URL_PATH) ?: '/'" />
                                    @break
                                @case('assumption')
                                    <x-provenance type="assumption" :source="($field['source'] ?? '') === 'live_ai' ? 'Live AI · '.($field['model'] ?? '') : 'Demo fixture'" />
                                    @break
                                @default
                                    <x-provenance :type="$field['provenance']" />
                            @endswitch
                        </dt>
                        <dd>
                            @if ($meta['list'])
                                <ul>
                                    @foreach ($profile->items($key) as $item)
                                        @php $itemSource = $field['item_sources'][$item] ?? null; @endphp
                                        <li>
                                            {{ $item }}
                                            @if ($itemSource && $itemSource !== ($field['source_url'] ?? null))
                                                <a href="{{ $itemSource }}" class="muted small" target="_blank" rel="noopener noreferrer">(from {{ parse_url($itemSource, PHP_URL_PATH) }})</a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                {{ $field['value'] }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>

            @if ($profile->social_urls)
                <p class="small"><strong>Social URLs you entered</strong> (recorded only; their content is not analysed in this proof of concept):
                    {{ collect($profile->social_urls)->map(fn ($u, $k) => \App\Models\BusinessProfile::SOCIAL_PLATFORMS[$k].': '.$u)->implode(' · ') }}
                </p>
            @endif

            @if ($profile->sources['pages'] ?? [])
                <details class="small">
                    <summary>Pages read ({{ count($profile->sources['pages']) }})</summary>
                    <ul>
                        @foreach ($profile->sources['pages'] as $page)
                            <li><a href="{{ $page }}" target="_blank" rel="noopener noreferrer">{{ $page }}</a></li>
                        @endforeach
                    </ul>
                    @if ($profile->sources['skipped'] ?? [])
                        <p class="muted">Skipped:</p>
                        <ul>
                            @foreach ($profile->sources['skipped'] as $skipped)
                                <li>{{ $skipped }}</li>
                            @endforeach
                        </ul>
                    @endif
                </details>
            @endif
        </section>

        <section class="card">
            <details @if ($errors->has('fields.*')) open @endif>
                <summary><strong>Edit profile</strong> <span class="muted small">— edited fields are labelled "Edited manually"; a confirmed profile returns to draft</span></summary>
                <form method="POST" action="{{ route('profile.update') }}" class="form">
                    @csrf
                    @method('PUT')
                    @foreach (\App\Models\BusinessProfile::FIELDS as $key => $meta)
                        <label for="edit_{{ $key }}">{{ $meta['label'] }}@if ($meta['list']) <span class="muted small">(one per line)</span>@endif</label>
                        <textarea id="edit_{{ $key }}" name="fields[{{ $key }}]" rows="{{ $meta['list'] ? 4 : 2 }}">{{ old("fields.{$key}", $profile->value($key)) }}</textarea>
                    @endforeach
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </form>
            </details>
            <p class="small"><a href="{{ route('profile.show', ['reanalyse' => 1]) }}">Read the website again</a> (replaces the current profile with a fresh draft)</p>
        </section>
    @endif
</x-layouts::app>
