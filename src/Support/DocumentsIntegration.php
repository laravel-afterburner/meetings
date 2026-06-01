<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Support\Relations\EmptyLinkedDocumentsRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DocumentsIntegration
{
    public static function isAvailable(): bool
    {
        return class_exists(\Afterburner\Documents\Models\Document::class)
            && class_exists(\Afterburner\Documents\Actions\LinkDocument::class)
            && Schema::hasTable('document_links');
    }

    public static function isEnabled(): bool
    {
        if (! config('afterburner-meetings.documents_enabled', true)) {
            return false;
        }

        return static::isAvailable();
    }

    public static function shouldPromptInstall(): bool
    {
        if (! config('afterburner-meetings.documents_enabled', true)) {
            return false;
        }

        return ! static::isAvailable();
    }

    /**
     * @return list<string>
     */
    public static function meetingEagerLoads(string $relation = 'linkedDocuments'): array
    {
        if (! static::isAvailable()) {
            return [];
        }

        return ["{$relation}.uploader"];
    }

    /**
     * @return Collection<int, mixed>
     */
    public static function linkedDocumentsFor(Model $model): Collection
    {
        if (! static::isAvailable()) {
            return collect();
        }

        if ($model->relationLoaded('linkedDocuments')) {
            return $model->getRelation('linkedDocuments');
        }

        return $model->linkedDocuments;
    }

    /**
     * @return list<array{name: string, filename: string}>
     */
    public static function linkedDocumentSummariesFor(Model $model): array
    {
        return static::linkedDocumentsFor($model)
            ->map(fn ($document) => [
                'name' => $document->name,
                'filename' => $document->filename,
            ])
            ->values()
            ->all();
    }

    /**
     * A no-op relation used when the documents package is not installed.
     */
    public static function emptyLinkedDocumentsRelation(Model $model): EmptyLinkedDocumentsRelation
    {
        return new EmptyLinkedDocumentsRelation($model);
    }
}
