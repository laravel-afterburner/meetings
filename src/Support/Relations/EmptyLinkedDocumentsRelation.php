<?php

namespace Afterburner\Meetings\Support\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Stand-in when the documents package or document_links table is unavailable.
 */
class EmptyLinkedDocumentsRelation extends Relation
{
    public function __construct(Model $parent)
    {
        parent::__construct($parent->newQuery(), $parent);
    }

    public function addConstraints(): void
    {
    }

    public function addEagerConstraints(array $models): void
    {
    }

    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection);
        }

        return $models;
    }

    public function match(array $models, Collection $results, $relation): array
    {
        return $this->initRelation($models, $relation);
    }

    public function getResults(): Collection
    {
        return new Collection;
    }

    public function get($columns = ['*'])
    {
        return $this->getResults();
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        return $query->whereRaw('0 = 1');
    }
}
