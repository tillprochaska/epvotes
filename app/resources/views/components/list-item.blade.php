@props([
    'avatarUrl' => null,
    'avatarStyle' => null,
    'title' => null,
    'subtitle' => null,
    'position' => null,
    'stats' => null,
])

<li {{ $attributes->bem('list-item') }}>
    @if ($avatarUrl)
    <x-avatar :url="$avatarUrl" class="list-item__avatar" :style="$avatarStyle" />
    @endif

    <div class="list-item__text">
        <strong>{{ $title }}</strong>
        <br>
        <span class="list-item__subtitle">{{ $subtitle }}</span>

        @if ($stats)
            <x-vote-result-chart :stats="$stats" style="slim" class="list-item__chart" />
        @endif
    </div>

    @if ($position)
        <span class="visually-hidden">
            {{ Str::lower($position->label) }}
        </span>

        <x-thumb :position="$position" style="circle" class="list-item__thumb"/>
    @endif
</li>
