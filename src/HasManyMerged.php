<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class HasManyMerged extends Relation
{
    /**
     * The foreign keys of the parent model.
     *
     * @var string[]
     */
    protected $foreignKeys;

    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * Create a new has one or many relationship instance.
     *
     * @param  Builder  $query
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
     * Set the base constraints on the relation query.
     * Note: Used to load relations of one model.
     *
     * @return void
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $foreignKeys = $this->foreignKeys;

            $this->query->where(function ($query) use ($foreignKeys): void {
                foreach ($foreignKeys as $foreignKey) {
                    $query->orWhere(function ($query) use ($foreignKey): void {
                        $query->where($foreignKey, '=', $this->getParentKey())
                            ->whereNotNull($foreignKey);
                    });
                }
            });
        }
    }

    /**
     * Get the key value of the parent's local key.
     * Info: From HasOneOrMany class.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->qualifyColumn($this->localKey);
    }

    /**
     * Set the constraints for an eager load of the relation.
     * Note: Used to load relations of multiple models at once.
     *
     * @param  array  $models
     */
    public function addEagerConstraints(array $models): void
    {
        $foreignKeys = $this->foreignKeys;
        $orWhereIn = $this->orWhereInMethod($this->parent, $this->localKey);

        $this->query->where(function ($query) use ($foreignKeys, $models, $orWhereIn): void {
            foreach ($foreignKeys as $foreignKey) {
                $query->{$orWhereIn}($foreignKey, $this->getKeys($models, $this->localKey));
            }
        });
    }

    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKeys = $this->foreignKeys;

        return $query->select($columns)->where(function ($query) use ($foreignKeys): void {
            foreach ($foreignKeys as $foreignKey) {
                $query->orWhere(function ($query) use ($foreignKey): void {
                    $query->whereColumn($this->getQualifiedParentKeyName(), '=', $foreignKey)
                        ->whereNotNull($foreignKey);
                });
            }
        });
    }

    /**
     * Get the name of the "where in" method for eager loading.
     * Note: Similar to whereInMethod of Relation class.
     *
     * @param  Model  $model
     * @param  string  $key
     * @return string
     */
    protected function orWhereInMethod(Model $model, string $key): string
    {
        return $model->getKeyName() === last(explode('.', $key))
        && in_array($model->getKeyType(), ['int', 'integer'])
            ? 'orWhereIntegerInRaw'
            : 'orWhereIn';
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
                    $this->related->newCollection($dictionary[$key])->unique($this->related->getKeyName())
                );
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     * Note: Custom code.
     *
     * @param  Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];
        $foreignKeyNames = $this->getForeignKeyNames();

        foreach ($results as $result) {
            foreach ($foreignKeyNames as $foreignKeyName) {
                $foreignKeyValue = $result->{$foreignKeyName};
                if (! isset($dictionary[$foreignKeyValue])) {
                    $dictionary[$foreignKeyValue] = [];
                }

                $dictionary[$foreignKeyValue][] = $result;
            }
        }

        return $dictionary;
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
     * @return mixed
     */
    public function getResults()
    {
        return $this->get();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        return parent::get($columns);
    }
}
