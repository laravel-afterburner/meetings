<?php

namespace Afterburner\Meetings\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case EligibleNotPresent = 'eligible_not_present';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::EligibleNotPresent => 'Not present',
        };
    }
}
