<?php

namespace Afterburner\Meetings\Enums;

enum MeetingType: string
{
    case Agm = 'agm';
    case Council = 'council';
    case Special = 'special';

    public function label(): string
    {
        return match ($this) {
            self::Agm => 'AGM',
            self::Council => 'Council',
            self::Special => 'Special',
        };
    }
}
