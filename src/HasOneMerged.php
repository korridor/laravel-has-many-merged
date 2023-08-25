<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TRelatedModel of Model
 * @extends HasOneOrManyMerged<TRelatedModel>
 */
class HasOneMerged extends HasOneOrManyMerged
{
    /**
     * Create a new has one or many relationship instance.
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  Model  $parent
     * @param  array  $foreignKeys
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $parent, array $foreignKeys, string $localKey)
    {
        $this->foreignKeys = $foreignKeys;
        $this->localKey = $localKey;

        parent::__construct($query, $parent);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array  $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        // TODO!!!

        // Info: From HasMany class
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     * Info: From HasMany class.
     *
     * @param  array  $models
     * @param  Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->getAttribute($this->localKey)])) {
                $model->setRelation(
                    $relation,
                    reset($dictionary[$key])
                );
            }
        }

        return $models;
    }

    /**
     * Get the plain foreign key.
     *
     * @return string[]
     */
    public function getForeignKeyNames(): array
    {
        return array_map(function (string $qualifiedForeignKeyName) {
            $segments = explode('.', $qualifiedForeignKeyName);

            return end($segments);
        }, $this->getQualifiedForeignKeyNames());
    }

    /**
     * Get the foreign key for the relationship.
     *
     * @return string[]
     */
    public function getQualifiedForeignKeyNames(): array
    {
        return $this->foreignKeys;
    }

    /**
     * Get the results of the relationship.
     *
     * @phpstan-return ?TRelatedModel
     */
    public function getResults()
    {
        return $this->first();
    }
}
