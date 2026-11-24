<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;

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
    use SupportsDefaultModels;

    /**
     * Initialize the relation on a set of models.
     * Info: From HasOne class.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultModelFor($model));
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     * The first model per parent (in query order) is used.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if ((is_int($key) || is_string($key)) && isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key][0]);
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
            return $this->getDefaultModelFor($this->parent);
        }

        return $this->query->first() ?: $this->getDefaultModelFor($this->parent);
    }

    /**
     * Get the default model (see withDefault()) or null if no default is set.
     *
     * @param  TDeclaringModel  $parent
     * @return ?TRelatedModel
     */
    protected function getDefaultModelFor(Model $parent): ?Model
    {
        /** @var ?TRelatedModel $default */
        $default = $this->getDefaultFor($parent);

        return $default;
    }

    /**
     * Make a new related instance for the given model.
     * No foreign key is set, because it is ambiguous which of the foreign keys should be used.
     *
     * @param  TDeclaringModel  $parent
     * @return TRelatedModel
     */
    protected function newRelatedInstanceFor(Model $parent)
    {
        return $this->related->newInstance();
    }
}
