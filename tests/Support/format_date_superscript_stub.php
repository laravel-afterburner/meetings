<?php

use Carbon\Carbon;

if (! function_exists('format_date_superscript')) {
    function format_date_superscript(Carbon $carbon, string $mode = 'date'): string
    {
        return match ($mode) {
            'datetime' => $carbon->format('M j, Y g:i A'),
            default => $carbon->format('M j, Y'),
        };
    }
}
