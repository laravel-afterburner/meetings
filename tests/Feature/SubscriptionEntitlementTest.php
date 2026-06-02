<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\SubscriptionEntitlementGate;
use Afterburner\Meetings\Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class SubscriptionEntitlementTest extends TestCase
{
    protected function enableSubscriptions(): void
    {
        config(['afterburner-subscriptions.enabled' => true]);
    }

    /**
     * @param  array<string, mixed>  $planFeatures
     */
    protected function applySubscriptionState(Team $team, User $user, ?\DateTimeInterface $trialEndsAt = null, array $planFeatures = []): Team
    {
        if ($trialEndsAt !== null) {
            $team->forceFill(['trial_ends_at' => $trialEndsAt])->save();
        }

        $configuredTeam = $team->fresh();

        if ($planFeatures !== []) {
            $configuredTeam->simulatePlanFeatures($planFeatures);
        }

        $user->setRelation('currentTeam', $configuredTeam);

        return $configuredTeam;
    }

    public function test_subscriptions_disabled_allows_meeting_access(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        config(['afterburner-subscriptions.enabled' => false]);

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Meeting::class));
        $this->assertTrue(Gate::forUser($user)->allows('create', [Meeting::class, $team]));

        app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Annual General Meeting',
            MeetingType::Agm,
            targetRoleSlugs: ['manager'],
        );

        $this->assertDatabaseCount('meetings', 1);
    }

    public function test_subscriptions_not_installed_allows_meeting_access(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $this->enableSubscriptions();

        $teamWithoutMethods = new class extends Model
        {
            protected $table = 'teams';
        };
        $teamWithoutMethods->forceFill(['id' => $team->id]);

        $this->assertTrue(SubscriptionEntitlementGate::allows($teamWithoutMethods));
    }

    public function test_generic_trial_allows_meeting_access_without_plan_feature(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $this->enableSubscriptions();
        $team = $this->applySubscriptionState(
            $team,
            $user,
            now()->addWeek(),
            ['features' => ['documents']],
        );

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Meeting::class));
        $this->assertTrue(Gate::forUser($user)->allows('create', [Meeting::class, $team]));

        app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Trial meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $this->assertDatabaseCount('meetings', 1);
    }

    public function test_expired_trial_without_meetings_entitlement_denies_access(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $this->enableSubscriptions();
        $team = $this->applySubscriptionState(
            $team,
            $user,
            now()->subDay(),
            ['features' => ['documents']],
        );

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', Meeting::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', [Meeting::class, $team]));

        $this->expectException(AuthorizationException::class);

        app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Blocked meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );
    }

    public function test_expired_trial_with_meetings_entitlement_and_permission_allows_access(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $this->enableSubscriptions();
        $team = $this->applySubscriptionState(
            $team,
            $user,
            now()->subDay(),
            ['features' => ['meetings']],
        );
        $team->simulateActiveSubscription();

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Meeting::class));
        $this->assertTrue(Gate::forUser($user)->allows('create', [Meeting::class, $team]));

        app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Allowed meeting',
            MeetingType::Special,
            targetRoleSlugs: ['manager'],
        );

        $this->assertDatabaseCount('meetings', 1);
    }

    public function test_expired_trial_with_meetings_entitlement_but_without_permission_denies_create(): void
    {
        [, $team] = $this->createTeamWithUser(['manage_meetings']);
        $member = $this->createAdditionalUser($team, [], 'member@example.com');
        $member->update(['current_team_id' => $team->id]);

        $this->enableSubscriptions();
        $team = $this->applySubscriptionState(
            $team,
            $member,
            now()->subDay(),
            ['features' => ['meetings']],
        );
        $team->simulateActiveSubscription();

        $this->assertTrue(Gate::forUser($member)->allows('viewAny', Meeting::class));
        $this->assertFalse(Gate::forUser($member)->allows('create', [Meeting::class, $team]));
    }

    public function test_within_limit_helper_respects_trial_and_plan_limits(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $this->enableSubscriptions();
        $team = $this->applySubscriptionState($team, $user, planFeatures: ['max_meetings' => 2]);
        $team->simulateActiveSubscription();

        $this->assertTrue(SubscriptionEntitlementGate::withinLimit($team, 'max_meetings', 2));
        $this->assertFalse(SubscriptionEntitlementGate::withinLimit($team, 'max_meetings', 3));

        $team = $this->applySubscriptionState(
            $team,
            $user,
            now()->addWeek(),
            ['max_meetings' => 2],
        );

        $this->assertTrue(SubscriptionEntitlementGate::withinLimit($team, 'max_meetings', 99));
    }
}
