<?php

if (! function_exists('format_date_superscript')) {
    /**
     * @param  \Carbon\Carbon|\DateTimeInterface|null  $date
     */
    function format_date_superscript($date, string $format = 'date'): string
    {
        if (! $date) {
            return '';
        }

        $carbon = $date instanceof \Carbon\Carbon
            ? $date
            : \Carbon\Carbon::parse($date);

        $base = $carbon->format('F j').'<sup>'.$carbon->format('S').'</sup>';

        return match ($format) {
            'date_no_year' => $base,
            'datetime_seconds' => $base.' '.$carbon->format('Y').' '.$carbon->format('g:i:s A'),
            'datetime' => $base.' '.$carbon->format('Y').' '.$carbon->format('g:i A'),
            default => $base.' '.$carbon->format('Y'),
        };
    }
}
