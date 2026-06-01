<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Models\Meeting;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

class MeetingPackagePdfExporter
{
    public function __construct(
        protected MeetingPackageDataBuilder $dataBuilder,
    ) {}

    public function isAvailable(): bool
    {
        return class_exists(Pdf::class);
    }

    public function output(Meeting $meeting): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException(
                'PDF export requires barryvdh/laravel-dompdf. Install it in the host application.'
            );
        }

        $data = $this->dataBuilder->build($meeting);

        return Pdf::loadView('afterburner-meetings::meetings.meeting-package-pdf', $data)->output();
    }
}
