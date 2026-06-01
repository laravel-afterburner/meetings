<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\AddSuggestedMeetingAgendaItems;
use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\CreateMeetingAgendaItem;
use Afterburner\Meetings\Actions\DeleteMeetingAgendaItem;
use Afterburner\Meetings\Actions\LinkMeetingAgendaReference;
use Afterburner\Meetings\Actions\ReorderMeetingAgendaItem;
use Afterburner\Meetings\Enums\AgendaSection;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use Afterburner\Meetings\Support\MeetingReferenceRegistry;
use Afterburner\Meetings\Tests\Support\FakeAgendaReference;
use Afterburner\Meetings\Tests\Support\FakeMeetingReferenceProvider;
use Afterburner\Meetings\Tests\TestCase;

class MeetingAgendaItemsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(MeetingReferenceRegistry::class)->register(new FakeMeetingReferenceProvider);
    }

    public function test_create_manual_agenda_item(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
        );

        $item = app(CreateMeetingAgendaItem::class)->execute(
            $meeting,
            $user,
            'Treasurer report',
            'Review quarterly finances.',
            AgendaSection::Reports,
        );

        $this->assertSame('Treasurer report', $item->title);
        $this->assertSame(AgendaSection::Reports, $item->section);
        $this->assertDatabaseHas('meeting_agenda_items', [
            'meeting_id' => $meeting->id,
            'title' => 'Treasurer report',
        ]);
    }

    public function test_link_reference_to_agenda(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $reference = FakeAgendaReference::query()->create([
            'team_id' => $team->id,
            'title' => 'Roof repair',
            'summary' => 'Obtain three quotes.',
        ]);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
        );

        $item = app(LinkMeetingAgendaReference::class)->execute(
            $meeting,
            $user,
            'fake_record',
            $reference->id,
            AgendaSection::NewBusiness,
        );

        $this->assertSame('Fake: Roof repair', $item->title);
        $this->assertSame($reference->id, $item->reference_id);
        $this->assertSame(FakeAgendaReference::class, $item->reference_type);
    }

    public function test_cannot_link_same_reference_twice(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $reference = FakeAgendaReference::query()->create([
            'team_id' => $team->id,
            'title' => 'Roof repair',
        ]);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
        );

        app(LinkMeetingAgendaReference::class)->execute(
            $meeting,
            $user,
            'fake_record',
            $reference->id,
        );

        $this->expectException(MeetingsException::class);

        app(LinkMeetingAgendaReference::class)->execute(
            $meeting,
            $user,
            'fake_record',
            $reference->id,
        );
    }

    public function test_suggested_items_skip_records_already_on_agenda(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $reference = FakeAgendaReference::query()->create([
            'team_id' => $team->id,
            'title' => 'Suggested topic A',
        ]);

        FakeAgendaReference::query()->create([
            'team_id' => $team->id,
            'title' => 'Suggested topic B',
        ]);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
        );

        app(LinkMeetingAgendaReference::class)->execute(
            $meeting,
            $user,
            'fake_record',
            $reference->id,
        );

        $created = app(AddSuggestedMeetingAgendaItems::class)->execute($meeting, $user);

        $this->assertCount(1, $created);
        $this->assertSame(2, MeetingAgendaItem::query()->where('meeting_id', $meeting->id)->count());
    }

    public function test_add_suggested_agenda_items(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        FakeAgendaReference::query()->create([
            'team_id' => $team->id,
            'title' => 'Suggested topic A',
        ]);

        FakeAgendaReference::query()->create([
            'team_id' => $team->id,
            'title' => 'Suggested topic B',
        ]);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
        );

        $created = app(AddSuggestedMeetingAgendaItems::class)->execute($meeting, $user);

        $this->assertCount(2, $created);
        $this->assertSame(2, MeetingAgendaItem::query()->where('meeting_id', $meeting->id)->count());
    }

    public function test_reorder_agenda_item(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
        );

        $first = app(CreateMeetingAgendaItem::class)->execute($meeting, $user, 'First item');
        $second = app(CreateMeetingAgendaItem::class)->execute($meeting, $user, 'Second item');

        app(ReorderMeetingAgendaItem::class)->execute($second, $user, 'up');

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_move_agenda_item_to_position(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
        );

        $first = app(CreateMeetingAgendaItem::class)->execute($meeting, $user, 'First item');
        $second = app(CreateMeetingAgendaItem::class)->execute($meeting, $user, 'Second item');
        $third = app(CreateMeetingAgendaItem::class)->execute($meeting, $user, 'Third item');

        app(ReorderMeetingAgendaItem::class)->moveToPosition($third, $user, 0);

        $this->assertSame(1, $third->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(3, $second->fresh()->sort_order);
    }

    public function test_delete_agenda_item(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
        );

        $item = app(CreateMeetingAgendaItem::class)->execute($meeting, $user, 'Remove me');

        app(DeleteMeetingAgendaItem::class)->execute($item, $user);

        $this->assertDatabaseMissing('meeting_agenda_items', ['id' => $item->id]);
    }
}
