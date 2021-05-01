<?php

use App\Actions\ScrapeVoteResultsAction;
use App\Document;
use App\Enums\DocumentTypeEnum;
use App\Enums\VotePositionEnum;
use App\Enums\VoteTypeEnum;
use App\Member;
use App\Procedure;
use App\Term;
use App\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    //TODO: use dep injection to mock ScrapeDocumentInfoAction
    Http::fakeJsonFromFile('*/document_info?type=B&term=9&number=154&year=2019', 'document_info.json');
    Http::fakeJsonFromFile('*/procedure?type=B&term=9&number=154&year=2019', 'procedure.json');

    $this->action = $this->app->make(ScrapeVoteResultsAction::class);
    $this->term = Term::factory(['number' => 9])->create();
    $this->date = new Carbon('2019-10-24');
});

it('creates new vote record including relations', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results.json');

    $this->action->execute($this->term, $this->date);

    expect(Vote::count())->toEqual(1);

    $vote = Vote::first();

    expect($vote->doceo_vote_id)->toEqual(109619);
    expect($vote->date)->toEqual(new Carbon('2019-10-24'));
    expect($vote->description)->toEqual('§ 1/2');
    expect($vote->term->id)->toEqual($this->term->id);
    expect($vote->type)->toEqual(VoteTypeEnum::SPLIT());
    expect($vote->subvote_description)->toEqual('§ 1/2');
    expect($vote->document)->not()->toBeNull();
});

it('creates new related document record with document infos', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results.json');

    $this->action->execute($this->term, $this->date);

    expect(Document::count())->toEqual(1);
    expect(Procedure::count())->toEqual(1);

    $document = Arr::except(Document::first()->getAttributes(), [
        'created_at',
        'updated_at',
        'id',
    ]);

    expect($document)->toEqual([
        'type' => DocumentTypeEnum::B(),
        'term_id' => $this->term->id,
        'number' => 154,
        'year' => 2019,
        'title' => 'MOTION FOR A RESOLUTION on search and rescue in the Mediterranean',
        'procedure_id' => Procedure::first()->id,
    ]);
});

it('finds and relates existing document record', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results.json');

    $document = Document::factory([
        'type' => DocumentTypeEnum::B(),
        'term_id' => $this->term->id,
        'number' => 154,
        'year' => 2019,
        'title' => 'MOTION FOR A RESOLUTION on search and rescue in the Mediterranean',
    ])->create();

    $this->action->execute($this->term, $this->date);

    expect(Document::count())->toEqual(1);
    expect(Vote::first()->document_id)->toEqual($document->id);
});

it('handles votes without document reference', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results-5.json');

    $this->action->execute($this->term, $this->date);

    expect(Document::count())->toEqual(0);
    expect(Vote::first()->document_id)->toBeNull();
});

it('updates existing vote record inlcuding relations', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results.json');

    $vote = Vote::factory([
        'doceo_vote_id' => 109619,
        'date' => $this->date,
        'term_id' => $this->term->id,
        'description' => 'Old Description',
        'document_id' => null,
    ])->create();

    $this->action->execute($this->term, $this->date);

    expect(Vote::count())->toEqual(1);
    expect(Document::count())->toEqual(1);
    expect($vote->fresh()->document)->toBeInstanceOf(Document::class);
});

it('finds and relates members with position', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results-2.json');

    $member = Member::factory([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ])->activeAt($this->date)->create();

    $this->action->execute($this->term, $this->date);

    $vote = Vote::first();

    expect($member->votes()->count())->toEqual(1);
    expect($vote->members()->count())->toEqual(1);

    $position = $member->votes()->first()->pivot->position;
    expect($position)->toEqual(VotePositionEnum::FOR());
});

it('updates related members', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results-2.json');

    $vote = Vote::factory([
        'doceo_vote_id' => 109619,
        'date' => $this->date,
        'term_id' => $this->term->id,
    ])->create();

    $member = Member::factory([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ])->activeAt($this->date)->create();

    $vote->members()->attach($member, [
        'position' => VotePositionEnum::AGAINST(),
    ]);

    $this->action->execute($this->term, $this->date);

    expect($vote->members()->count())->toEqual(1);

    $position = $vote->members()->first()->pivot->position;
    $expected = VotePositionEnum::FOR();

    expect($position)->toEqual($expected);
});

it('ignores inactive members', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results-2.json');

    $inactiveMember = Member::factory([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ])->create();

    $activeMember = Member::factory([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ])->activeAt($this->date)->create();

    $this->action->execute($this->term, $this->date);

    $vote = Vote::first();

    expect($vote->members()->count())->toEqual(1);
    expect($vote->members()->first()->id)->toEqual($activeMember->id);
});

it('finds members by first and last name if ambiguous', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results-3.json');

    $jane = Member::factory([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ])->activeAt($this->date)->create();

    $john = Member::factory([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ])->activeAt($this->date)->create();

    $this->action->execute($this->term, $this->date);

    expect(Vote::count())->toEqual(1);

    $members = Vote::first()->members();

    $positionJane = (clone $members)
        ->whereFirstName('Jane')
        ->first()
        ->pivot
        ->position;

    $positionJohn = (clone $members)
        ->whereFirstName('John')
        ->first()
        ->pivot
        ->position;

    expect($members->count())->toEqual(2);
    expect($positionJane)->toEqual(VotePositionEnum::FOR());
    expect($positionJohn)->toEqual(VotePositionEnum::AGAINST());
});

it('finds members using case-insensitive comparisons', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results-2.json');
    Member::factory(['last_name' => 'DOE'])->activeAt($this->date)->create();

    $this->action->execute($this->term, $this->date);

    expect(Vote::first()->members()->first()->last_name)->toEqual('DOE');
});

it('finds members with special characters in name', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results-4.json');
    Member::factory(['last_name' => 'DOÉ'])->activeAt($this->date)->create();

    $this->action->execute($this->term, $this->date);

    expect(Vote::first()->members()->first()->last_name)->toEqual('DOÉ');
});

it('compiles vote stats', function () {
    Http::fakeJsonFromFile('*/vote_results?term=9&date=2019-10-24', 'vote_results-3.json');

    $jane = Member::factory([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ])->activeAt($this->date)->create();

    $john = Member::factory([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ])->activeAt($this->date)->create();

    $this->action->execute($this->term, $this->date);

    expect(Vote::first()->stats['voted'])->toEqual(2);
});