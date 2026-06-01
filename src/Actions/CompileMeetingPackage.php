<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Documents\Actions\FinalizeDocumentUpload;
use Afterburner\Documents\Actions\LinkDocument;
use Afterburner\Documents\Actions\UploadDocument;
use Afterburner\Documents\Models\Document;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\DocumentsIntegration;
use Afterburner\Meetings\Support\MeetingPackagePdfExporter;
use Afterburner\Meetings\Support\MeetingsDocumentFolder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CompileMeetingPackage
{
    public function __construct(
        protected MeetingPackagePdfExporter $pdfExporter,
    ) {}

    public function execute(Meeting $meeting, User $user): Document
    {
        if (! DocumentsIntegration::isEnabled()) {
            throw new MeetingsException('Document storage is not available.');
        }

        if (! $this->pdfExporter->isAvailable()) {
            throw new MeetingsException(
                'PDF compilation requires barryvdh/laravel-dompdf in the host application.'
            );
        }

        Gate::forUser($user)->authorize('compilePackage', $meeting);

        if ($meeting->status !== MeetingStatus::Completed) {
            throw new MeetingsException('Only completed meetings can be compiled into a document package.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'meeting-package-');

        try {
            file_put_contents($tempPath, $this->pdfExporter->output($meeting));

            $filename = $this->buildFilename($meeting);
            $size = (int) filesize($tempPath);
            $folder = MeetingsDocumentFolder::resolve($meeting->team_id, $user);

            $document = app(UploadDocument::class)->execute(
                $meeting->team_id,
                $folder->id,
                $filename,
                'application/pdf',
                $size,
                $user,
            );

            $document = app(FinalizeDocumentUpload::class)->executeFromPath($document, $tempPath, $user);

            app(LinkDocument::class)->execute($document, $meeting, $user);

            return $document;
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    protected function buildFilename(Meeting $meeting): string
    {
        $slug = Str::slug($meeting->title) ?: 'meeting';
        $date = $meeting->scheduled_at?->format('Y-m-d') ?? now()->format('Y-m-d');

        return "{$slug}-{$date}-package.pdf";
    }
}
