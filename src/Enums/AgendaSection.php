<?php

namespace Afterburner\Meetings\Enums;

enum AgendaSection: string
{
    case CallToOrder = 'call_to_order';
    case OldBusiness = 'old_business';
    case Reports = 'reports';
    case NewBusiness = 'new_business';
    case Resolutions = 'resolutions';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::CallToOrder => 'Call to order',
            self::OldBusiness => 'Old business',
            self::Reports => 'Reports',
            self::NewBusiness => 'New business',
            self::Resolutions => 'Resolutions',
            self::General => 'General',
        };
    }
}
