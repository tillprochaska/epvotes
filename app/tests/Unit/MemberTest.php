<?php

use App\Group;
use App\GroupMembership;
use App\Member;
use App\Term;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('ensures `web_id` is unique', function () {
    Member::factory()->createMany([
        ['web_id' => 12345],
        ['web_id' => 12345],
    ]);
})->throws(QueryException::class);

it('is associated with a single group per start date', function () {
    $member = Member::factory()->create();

    GroupMembership::factory([
        'member_id' => $member->id,
        'start_date' => '2020-01-01',
    ])->count(2)->create();
})->throws(QueryException::class);

it('converts date_of_birth to date object', function () {
    $member = new Member([
        'date_of_birth' => '1975-01-01',
    ]);

    expect($member->date_of_birth)->toBeInstanceOf(DateTime::class);
});

it('merges terms', function () {
    Term::factory()->createMany([
        ['number' => 8],
        ['number' => 9],
        ['number' => 10],
    ]);

    $oldTerms = Term::whereIn('number', [8, 9]);
    $newTerms = Term::whereIn('number', [9, 10]);

    $member = Member::factory(['web_id' => 12345])->create();
    $member->terms()->attach($oldTerms->pluck('id'));

    $result = $member->mergeTerms($newTerms);
    $termNumbers = $member->terms()->pluck('number')->toArray();

    expect($result)->toBe($member);
    expect($termNumbers)->toEqual([8, 9, 10]);
});

it('filters active members for given date', function () {
    $date = new Carbon('2020-01-02');
    $before = new Carbon('2020-01-01');
    $after = new Carbon('2020-01-03');

    Member::factory()->create();
    Member::factory()->activeAt($before)->create();
    Member::factory()->activeAt($after)->create();
    $active = Member::factory()->activeAt($date)->create();

    expect(Member::activeAt($date)->count())->toEqual(1);
    expect(Member::activeAt($date)->first()->is($active))->toBeTrue();
});

it('normalizes names', function () {
    expect(Member::normalizeName('ALL UPPERCASE'))->toEqual('all uppercase');
    expect(Member::normalizeName('ÄÖÜäöü'))->toEqual('äöüäöü');
    expect(Member::normalizeName('removes-dashes'))->toEqual('removes dashes');
    expect(Member::normalizeName('Nienaß'))->toEqual('nienass');
});

it('automatically updates normalized name columns', function () {
    $member = Member::factory([
        'first_name' => 'ALL',
        'last_name' => 'UPPERCASE',
    ])->create();

    $first = $member->first_name_normalized;
    $last = $member->last_name_normalized;

    expect($first)->toEqual(Member::normalizeName($member->first_name));
    expect($last)->toEqual(Member::normalizeName($member->last_name));
});

it('loads with group membership at date', function () {
    $greens = Group::factory(['code' => 'GREENS'])->create();
    $epp = Group::factory(['code' => 'EPP'])->create();

    $member = Member::factory()
        ->activeAt(Carbon::yesterday(), $greens)
        ->activeAt(Carbon::today(), $epp)
        ->create();

    $yesterday = Member::withGroupMembershipAt(Carbon::yesterday())->first();
    $today = Member::withGroupMembershipAt(Carbon::today())->first();
    $tomorrow = Member::withGroupMembershipAt(Carbon::tomorrow())->first();

    expect($yesterday->group_id)->toEqual($greens->id);
    expect($today->group_id)->toEqual($epp->id);

    // As the member’s not active tomorrow, they don’t have an
    // associated group for that day
    expect($tomorrow->group_id)->toBeNull();
});
