<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Returns a single related model that matches any of the foreign keys.
 * Combine it with an ordering (for example ->latest()) to pick a specific model.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @extends HasOneOrManyMerged<TRelatedModel, TDeclaringModel, ?TRelatedModel>
 */
class HasOneMerged extends HasOneOrManyMerged
{
    /**
     * Initialize the relation on a set of models.
     * Info: From HasOne class.
     *
     * @param  array  $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     * The first model per parent (in query order) is used.
     *
     * @param  array  $models
     * @param  Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->getAttribute($this->localKey)])) {
                $model->setRelation($relation, reset($dictionary[$key]));
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return ?TRelatedModel
     */
    public function getResults()
    {
        if ($this->getParentKey() === null) {
            return null;
        }

        return $this->query->first();
    }
}
