@props([
    'avatarUrl' => null,
    'title' => null,
    'subtitle' => null,
    'position' => null
])

<li {{ $attributes->bem('list-item') }}>
    <x-avatar :url="$avatarUrl" class="list-item__avatar" />

    <div class="list-item__text">
        <strong>{{ $title }}</strong>
        <br>
        {{ $subtitle }}
    </div>

    <span class="visually-hidden">
        {{ Str::lower($position->label) }}
    </span>

    <x-thumb :position="$position" style="circle" class="list-item__thumb"/>
</li>
