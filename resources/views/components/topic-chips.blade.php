@props(['topics', 'selected', 'route'])

{{-- Toggleable topic filter chips; ?topics=1,3 narrows the page. --}}
@if ($topics->count() > 1)
    <div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-center gap-2']) }}>
        @foreach ($topics as $topic)
            @php
                $active = in_array($topic->id, $selected, true);
                $next = collect($selected)
                    ->when($active,
                        fn ($ids) => $ids->reject(fn ($id) => $id === $topic->id),
                        fn ($ids) => $ids->push($topic->id))
                    ->sort()->values();
                $params = request()->except(['topics', 'page']);
                if ($next->isNotEmpty()) {
                    $params['topics'] = $next->implode(',');
                }
            @endphp
            <a
                href="{{ route($route, $params) }}"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium focus:outline-2 focus:outline-offset-2 focus:outline-focus {{ $active ? 'border-ink bg-panel text-ink' : 'border-edge bg-surface text-ink-soft hover:text-ink' }}"
                aria-pressed="{{ $active ? 'true' : 'false' }}"
            >
                <x-topic-dot :topic="$topic" />
                {{ $topic->name }}
            </a>
        @endforeach
        @if (! empty($selected))
            <a href="{{ route($route, request()->except(['topics', 'page'])) }}" class="px-1 text-sm font-medium text-ink-soft hover:text-ink focus:outline-2 focus:outline-offset-2 focus:outline-focus">Clear</a>
        @endif
    </div>
@endif
