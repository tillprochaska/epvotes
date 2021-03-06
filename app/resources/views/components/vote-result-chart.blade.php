@props([
    'stats' => null,
    'style' => null,
])

<x-stack class="stack--xxs">
    <div {{ $attributes->bem('vote-result-chart', $style) }}>
        @if ($stats['by_position']['FOR'] > 0)
        <x-vote-result-chart-bar
            :value="$stats['by_position']['FOR']"
            :total="$stats['voted']"
            :style="$style"
            position="for"
        />
        @endif

        @if ($stats['by_position']['AGAINST'] > 0)
        <x-vote-result-chart-bar
            :value="$stats['by_position']['AGAINST']"
            :total="$stats['voted']"
            :style="$style"
            position="against"
        />
        @endif

        @if ($stats['by_position']['ABSTENTION'] > 0)
        <x-vote-result-chart-bar
            :value="$stats['by_position']['ABSTENTION']"
            :total="$stats['voted']"
            :style="$style"
            position="abstention"
        />
        @endif
    </div>

    @if ($style !== 'slim')
        <p class="text--xs">
            <span class="text--green">
                <strong>@lang('voting-lists.for'): {{ $stats['by_position']['FOR'] }}</strong>
                <x-thumb position="for" />
            </span>

            |

            <span class="text--red">
                <strong>@lang('voting-lists.against'): {{ $stats['by_position']['AGAINST'] }}</strong>
                <x-thumb position="against" />
            </span>

            |

            <span class="text--blue">
                <strong>@lang('voting-lists.abstentions'): {{ $stats['by_position']['ABSTENTION'] }}</strong>
                <x-thumb position="abstention" />
            </span>

            |

            @lang('voting-lists.share-picture.summary', [
                'voted' => $stats['voted'],
                'did-not-vote' => $stats['by_position']['NOVOTE'],
            ])
        </p>
    @endif
</x-stack>
