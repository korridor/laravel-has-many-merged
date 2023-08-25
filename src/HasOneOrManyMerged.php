<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @template TRelatedModel of Model
 * @extends Relation<TRelatedModel>
 */
abstract class HasOneOrManyMerged extends Relation
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
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     * Note: Custom code.
     *
     * @param Builder $query
     * @param Builder $parentQuery
     * @param  array|mixed  $columns
     * @return Builder
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
     * Info: From HasOneOrMany class.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->qualifyColumn($this->localKey);
    }

    /**
     * Get the plain foreign key.
     * Note: Custom code.
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
     * Note: Custom code.
     *
     * @return string[]
     */
    public function getQualifiedForeignKeyNames(): array
    {
        return $this->foreignKeys;
    }
}
