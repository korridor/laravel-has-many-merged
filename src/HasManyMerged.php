<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @extends Relation<TRelatedModel, TDeclaringModel, Collection<int, TRelatedModel>>
 */
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
     * @param  Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string[]  $foreignKeys
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $parent, array $foreignKeys, string $localKey)
    {
        if ($foreignKeys === []) {
            // Without foreign keys the constraint closure is empty and the query would return every row.
            throw new InvalidArgumentException('HasManyMerged requires at least one foreign key.');
        }

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
     * @param  array<int, TDeclaringModel>  $models
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
     * @param  Builder<TRelatedModel>  $query
     * @param  Builder<TDeclaringModel>  $parentQuery
     * @param  array|mixed  $columns
     * @return Builder<TRelatedModel>
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
     * Build model dictionary keyed by the relation's foreign key.
     * Note: Custom code.
     *
     * @param  Collection<int, TRelatedModel>  $results
     * @return array<array-key, list<TRelatedModel>>
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];
        $foreignKeyNames = $this->getForeignKeyNames();

        foreach ($results as $result) {
            foreach ($foreignKeyNames as $foreignKeyName) {
                $foreignKeyValue = $result->{$foreignKeyName};
                if ($foreignKeyValue === null) {
                    continue;
                }
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
