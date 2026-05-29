<?php

namespace Afterburner\Meetings\Models;

use Afterburner\Meetings\Concerns\HasLinkedDocuments;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Support\MeetingAudienceService;
use Afterburner\Meetings\Support\VotingIntegration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Meeting extends Model
{
    use HasLinkedDocuments;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'title',
        'type',
        'status',
        'scheduled_at',
        'location',
        'virtual_link',
        'agenda_notes',
        'target_role_slugs',
        'minutes',
        'minutes_finalized_at',
        'minutes_finalized_by_user_id',
        'invitations_sent_at',
        'settings',
    ];

    protected $casts = [
        'type' => MeetingType::class,
        'status' => MeetingStatus::class,
        'scheduled_at' => 'datetime',
        'target_role_slugs' => 'array',
        'minutes_finalized_at' => 'datetime',
        'invitations_sent_at' => 'datetime',
        'settings' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function minutesFinalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'minutes_finalized_by_user_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function meetingBallots(): HasMany
    {
        return $this->hasMany(MeetingBallot::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return Collection<int, User>
     */
    public function invitedUsers(): Collection
    {
        return app(MeetingAudienceService::class)->invitedUsers($this);
    }

    public function linkedBallots()
    {
        if (! VotingIntegration::isAvailable()) {
            return collect();
        }

        $ballotClass = VotingIntegration::ballotModelClass();
        $ballotIds = $this->meetingBallots()->pluck('ballot_id');

        return $ballotClass::query()
            ->where('team_id', $this->team_id)
            ->whereIn('id', $ballotIds)
            ->orderBy('title')
            ->get();
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function minutesAreEditable(): bool
    {
        return $this->minutes_finalized_at === null
            && in_array($this->status, [MeetingStatus::InProgress, MeetingStatus::Completed], true);
    }

    public function recordBallotEvent(int $ballotId, string $event): void
    {
        $settings = $this->settings ?? [];
        $settings['ballot_events'] ??= [];
        $settings['ballot_events'][(string) $ballotId] = [
            'event' => $event,
            'at' => now()->toIso8601String(),
        ];

        $this->forceFill(['settings' => $settings])->save();
    }
}
