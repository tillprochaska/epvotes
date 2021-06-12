<?php

use App\Enums\CountryEnum;
use App\Enums\VoteResultEnum;
use App\Group;
use App\Member;
use App\Vote;
use App\VoteCollection;
use App\VotingList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $date = new Carbon('2021-05-23');

    $this->greens = Group::factory([
        'code' => 'GREENS',
        'name' => 'Greens/European Free Alliance',
        'abbreviation' => 'Greens/EFA',
    ])->create();

    $this->sd = Group::factory([
        'code' => 'SD',
        'name' => 'Progressive Alliance of Socialists and Democrats',
        'abbreviation' => 'S&D',
    ])->create();

    $greensFor = Member::factory([
        'first_name' => 'Jane',
        'last_name' => 'DOE',
        'country' => CountryEnum::NL(),
    ])->activeAt($date, $this->greens)->count(1);

    $sdAgainst = Member::factory([
        'first_name' => 'Martin',
        'last_name' => 'EISENBAHN',
        'country' => CountryEnum::DE(),
    ])->activeAt($date, $this->sd)->count(2);

    $votingListData = [
        'id' => 1,
        'description' => 'Matched Voting List',
        'date'=> $date,
        'vote_id' => Vote::factory([
            'result' => VoteResultEnum::ADOPTED(),
            'vote_collection_id' => VoteCollection::factory(['title' => 'My Title']),
        ]),
    ];

    $this->votingList = VotingList::factory($votingListData)
        ->withStats()
        ->withMembers('FOR', $greensFor)
        ->withMembers('AGAINST', $sdAgainst)
        ->create();
});

it('does not fail if there is no associated vote', function () {
    $votingList = VotingList::factory([
        'id' => 10,
        'vote_id' => null,
        'description' => 'Unmatched Voting List',
    ])->withStats()->create();

    $response = $this->get('/votes/10');
    expect($response)->toHaveStatus(200);
});

it('shows a title', function () {
    $response = $this->get('/votes/1');
    expect($response)->toHaveSelectorWithText('h1', 'My Title');
});

it('shows the result of the vote', function () {
    $response = $this->get('/votes/1');
    expect($response)->toHaveSelectorWithText('h1 + p', 'adopted');
    expect($response)->toHaveSelector('h1 + p > .thumb--adopted.thumb--circle');
});

it('displays a bar chart', function () {
    $response = $this->get('/votes/1');
    expect($response)->toHaveSelector('.vote-result-chart');
});

it('shows a list of members', function () {
    $response = $this->get('/votes/1');

    expect($response)->toHaveSelectorWithText('.list-item__text', 'Jane DOE');
    expect($response)->toHaveSelectorWithText('.list-item__text', 'Greens/EFA · Netherlands');
    expect($response)->toHaveSelector('.thumb--for.thumb--circle.list-item__thumb');
});

it('shows a list of groups sorted descending by number of active members', function () {
    $this->votingList->stats = array_merge($this->votingList->stats, [
        'by_group' => [
            $this->sd->id => [
                'active' => 100,
                'voted' => 25,
                'by_position' => [
                    'FOR' => 25,
                    'AGAINST' => 0,
                    'ABSTENTION' => 0,
                    'ABSENT' => 0,
                ],
            ],
            $this->greens->id => [
                'active' => 50,
                'voted' => 50,
                'by_position' => [
                    'FOR' => 50,
                    'AGAINST' => 0,
                    'ABSTENTION' => 0,
                    'ABSENT' => 0,
                ],
            ],
        ],
    ]);

    $this->votingList->save();
    $response = $this->get('/votes/1');

    expect($response)->toSeeInOrder([
        'Progressive Alliance of Socialists and Democrats',
        'Greens/European Free Alliance',
    ]);
});

it('shows all countries of which MEPs participated sorted descending by number of active members', function () {
    $this->votingList->stats = array_merge($this->votingList->stats, [
        'by_country' => [
            'DE' => [
                'active' => 100,
                'voted' => 25,
                'by_position' => [
                    'FOR' => 25,
                    'AGAINST' => 0,
                    'ABSTENTION' => 0,
                    'ABSENT' => 0,
                ],
            ],
            'NL' => [
                'active' => 200,
                'voted' => 50,
                'by_position' => [
                    'FOR' => 50,
                    'AGAINST' => 0,
                    'ABSTENTION' => 0,
                    'ABSENT' => 0,
                ],
            ],
        ],
    ]);

    $this->votingList->save();
    $response = $this->get('/votes/1');

    expect($response)->toSeeInOrder([
        'Netherlands',
        'Germany',
    ]);
});
