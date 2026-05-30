<?php

namespace Afterburner\Meetings\Models;

use Afterburner\Meetings\Contracts\MeetingReferenceProvider;
use Afterburner\Meetings\Enums\AgendaSection;
use Afterburner\Meetings\Support\MeetingReferenceRegistry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MeetingAgendaItem extends Model
{
    protected $fillable = [
        'meeting_id',
        'team_id',
        'title',
        'notes',
        'section',
        'reference_type',
        'reference_id',
        'sort_order',
        'created_by_user_id',
    ];

    protected $casts = [
        'section' => AgendaSection::class,
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasReference(): bool
    {
        return $this->reference_type !== null && $this->reference_id !== null;
    }

    public function referenceProvider(): ?MeetingReferenceProvider
    {
        if (! $this->hasReference()) {
            return null;
        }

        $reference = $this->reference;

        if ($reference === null) {
            return null;
        }

        return app(MeetingReferenceRegistry::class)->forModel($reference);
    }

    public function referenceLabel(): ?string
    {
        return $this->referenceProvider()?->label();
    }

    public function displaySummary(): ?string
    {
        if (filled($this->notes)) {
            return $this->notes;
        }

        $reference = $this->reference;

        if ($reference === null) {
            return null;
        }

        return $this->referenceProvider()?->agendaSummary($reference);
    }

    public function referenceViewUrl(): ?string
    {
        $reference = $this->reference;

        if ($reference === null) {
            return null;
        }

        return $this->referenceProvider()?->viewUrl($this->team, $reference);
    }
}
