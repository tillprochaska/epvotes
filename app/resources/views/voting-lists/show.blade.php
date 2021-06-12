<x-app title="{{ $votingList->displayTitle }} · Vote Result">
    <x-wrapper>
        <x-stack>
            <x-stack space="xs">
                <h1 class="alpha">{{ $votingList->display_title }}</h1>
                <p>
                    {{ $votingList->date->formatLocalized('%b %e, %Y') }}

                    @if ($votingList->vote)
                        ·
                        {{ Str::lower($votingList->vote->result->label) }}
                        <x-thumb style="circle" :result="$votingList->vote->result" />
                    @endif
                </p>

                <x-vote-result-chart :stats="$votingList->stats"/>
            </x-stack>
            <x-list>
                @foreach ($countries as $country => $stats)
                    <x-list-item
                        :title="\App\Enums\CountryEnum::make($country)->label"
                        :subtitle="__('voting-lists.groups.count', [
                            'voted' => $stats['voted'],
                            'total' => $stats['active'],
                        ])"
                        :stats="$stats"
                        avatarStyle="squared"
                        avatarUrl="/assets/countries/{{ Str::lower($country) }}.svg"
                    />
                @endforeach
            </x-list>
            <x-list>
                @foreach ($groups as $group)
                    <x-list-item
                        :title="$group->name"
                        :subtitle="__('voting-lists.groups.count', [
                            'voted' => $group->stats['voted'],
                            'total' => $group->stats['active'],
                        ])"
                        :stats="$group->stats"
                        avatarStyle="squared"
                        avatarUrl="/assets/groups/{{ Str::lower($group->code) }}.svg"
                    />
                @endforeach
            </x-list>
            <x-list>
                @foreach ($members as $member)
                    <x-list-item
                        avatar-url="https://www.europarl.europa.eu/mepphoto/{{ $member->web_id }}.jpg"
                        {{-- TODO --}}
                        {{-- using expressions instead of double brackets stops double escape --}}
                        :title="$member->first_name.' '.$member->last_name"
                        :subtitle="$member->group->abbreviation.' · '.$member->country->label"
                        :position="$member->pivot->position"
                    />
                @endforeach
            </x-list>

        </x-stack>
    </x-wrapper>


</x-app>
