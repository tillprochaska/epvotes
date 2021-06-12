<?php

namespace App\Http\Controllers;

use App\Enums\CountryEnum;
use App\Group;
use App\VotingList;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Spatie\Url\Url;

class VotingListsController extends Controller
{
    public function show(VotingList $votingList)
    {
        $stats = $votingList->stats;

        $groups = Group::all()
            ->map(function ($group) use ($stats) {
                $group->stats = $stats['by_group'][$group->id] ?? null;

                return $group;
            })
            ->filter(function ($group) {
                return $group->stats && $group->stats['active'] > 0;
            })
            ->sortByDesc(function ($group) {
                return $group->stats['active'];
            });

        $countries = collect(CountryEnum::toValues())
            ->map(function ($country) use ($stats) {
                $countryStats = $stats['by_country'][$country] ?? null;

                return [$country, $countryStats];
            })
            ->toAssoc()
            ->filter(function ($countryStats) {
                return $countryStats && $countryStats['active'] > 0;
            })
            ->sortByDesc(function ($countryStats) {
                return $countryStats['active'];
            });

        return view('voting-lists.show', [
            'votingList' => $votingList,
            'members' => $this->members($votingList),
            'groups' => $groups,
            'countries' => $countries,
        ]);
    }

    public function sharePicture(VotingList $votingList)
    {
        $shortUrl = Url::fromString(route('voting-list.short', ['hashId' => $votingList->hashId]));
        $shortDisplayUrl = $shortUrl->getHost().$shortUrl->getPath();

        return view('voting-lists.share-picture', [
            'votingList' => $votingList,
            'shortDisplayUrl' => $shortDisplayUrl,
        ]);
    }

    public function csv(VotingList $votingList)
    {
        $rows = $this->members($votingList)->map(function ($member) {
            return [
                'member_id' => $member->member_id,
                'last_name' => $member->last_name,
                'first_name' => $member->first_name,
                'group.abbreviation' => $member->group->abbreviation,
                'group.name' => $member->group->name,
                'country.code' => $member->country->value,
                'country.name' => $member->country->label,
                'position' => $member->pivot->position->label,
            ];
        })->toArray();

        return response()->stream(function () use ($votingList, $rows) {
            $writer = SimpleExcelWriter::createWithoutBom('php://output', 'csv');
            $writer->addRows($rows)->close();
        }, 200, ['Content-Type' => 'text/csv']);
    }

    private function members(VotingList $votingList)
    {
        return $votingList
            ->members()
            ->withGroupMembershipAt($votingList->date)
            ->with('group')
            ->select('*')
            ->get();
    }
}
