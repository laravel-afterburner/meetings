<?php

namespace Afterburner\Meetings\Enums;

enum MeetingStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    /**
     * @return string Tailwind classes for status badges
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            self::Scheduled => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            self::InProgress => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            self::Completed => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            self::Cancelled => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        };
    }

    public function listCategoryLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled, self::InProgress => 'Scheduled',
            self::Completed, self::Cancelled => 'Past',
        };
    }

    /**
     * @return string Tailwind classes for index list category badges
     */
    public function listCategoryBadgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            self::Scheduled, self::InProgress => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            self::Completed, self::Cancelled => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
        };
    }
}
