<?php

namespace Afterburner\Meetings\Concerns;

use Afterburner\Meetings\Support\DocumentsIntegration;
use Illuminate\Database\Eloquent\Relations\Relation;

trait HasLinkedDocuments
{
    public function linkedDocuments(): Relation
    {
        if (! DocumentsIntegration::isAvailable()) {
            return DocumentsIntegration::emptyLinkedDocumentsRelation($this);
        }

        return $this->morphToMany(
            \Afterburner\Documents\Models\Document::class,
            'linkable',
            'document_links',
            'linkable_id',
            'document_id'
        )
            ->withTimestamps()
            ->withPivot(['team_id', 'linked_by_user_id'])
            ->orderBy('document_links.created_at');
    }
}
