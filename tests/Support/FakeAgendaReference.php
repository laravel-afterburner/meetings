<?php

namespace Afterburner\Meetings\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class FakeAgendaReference extends Model
{
    protected $table = 'fake_agenda_references';

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'title',
        'summary',
    ];
}
