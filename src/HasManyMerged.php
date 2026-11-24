<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @extends HasOneOrManyMerged<TRelatedModel, TDeclaringModel, Collection<int, TRelatedModel>>
 */
class HasManyMerged extends HasOneOrManyMerged
{
    /**
     * Initialize the relation on a set of models.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function initRelation(array $models, $relation)
    {
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
     * @param  array<int, TDeclaringModel>  $models
     * @param  Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if ((is_int($key) || is_string($key)) && isset($dictionary[$key])) {
                $model->setRelation(
                    $relation,
                    $this->related->newCollection($dictionary[$key])->unique($this->related->getKeyName())->values()
                );
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return Collection<int, TRelatedModel>
     */
    public function getResults()
    {
        /** @var Collection<int, TRelatedModel> $results */
        $results = $this->get();

        return $results;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  string[]  $columns
     * @return Collection<int, TRelatedModel>
     */
    public function get($columns = ['*'])
    {
        return parent::get($columns);
    }
}
