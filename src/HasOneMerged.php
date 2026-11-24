<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use InvalidArgumentException;
use WeakMap;

/**
 * Returns a single related model that matches any of the foreign keys.
 * Without ofMany() it behaves like Laravel's hasOne: combine it with an ordering (for example ->latest())
 * for lazy loading, or use latestOfMany(), oldestOfMany() or ofMany() to pick one specific model per parent
 * that is also respected by eager loading and existence queries.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @extends HasOneOrManyMerged<TRelatedModel, TDeclaringModel, ?TRelatedModel>
 */
class HasOneMerged extends HasOneOrManyMerged
{
    use SupportsDefaultModels;

    /**
     * Name of the column that holds the value of the matched foreign key in the one of many subqueries.
     */
    public const ONE_OF_MANY_KEY = 'laravel_merged_key';

    /**
     * Determines whether the relationship is one-of-many.
     * Info: From CanBeOneOfMany trait.
     *
     * @var bool
     */
    protected $isOneOfMany = false;

    /**
     * The name of the relationship (used as alias of the one of many subquery join).
     * Info: From CanBeOneOfMany trait.
     *
     * @var string
     */
    protected $relationName = '';

    /**
     * The first one of many subquery.
     * Info: From CanBeOneOfMany trait.
     *
     * @var Builder<TRelatedModel>|null
     */
    protected $oneOfManySubQuery;

    /**
     * One query per foreign key and one of many subquery, used to build the union of all foreign keys.
     * The relationship constraints are added to these queries.
     *
     * @var list<array{query: Builder<TRelatedModel>, foreignKey: string}>
     */
    protected $oneOfManyForeignKeyQueries = [];

    /**
     * The value of the matched foreign key for every one of many result.
     *
     * @var WeakMap<Model, mixed>|null
     */
    protected $oneOfManyKeys;

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
     * Without ofMany() the first model per parent (in query order) is used.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->isOneOfMany ? $this->buildOneOfManyDictionary($results) : $this->buildDictionary($results);

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

        if (! $this->isOneOfMany) {
            return $this->query->first() ?: $this->getDefaultModelFor($this->parent);
        }

        $this->addOneOfManyKeySelect();

        $result = $this->query->first();
        if ($result !== null) {
            $this->extractOneOfManyKey($result);
        }

        return $result ?: $this->getDefaultModelFor($this->parent);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  string[]  $columns
     * @return Collection<int, TRelatedModel>
     */
    public function get($columns = ['*'])
    {
        if (! $this->isOneOfMany) {
            return $this->query->get($columns);
        }

        $this->addOneOfManyKeySelect();

        $results = $this->query->get($columns);

        foreach ($results as $result) {
            $this->extractOneOfManyKey($result);
        }

        return $results;
    }

    /**
     * Select the matched foreign key of the one of many subquery.
     * It is needed to match the results to their parents, because a related model can be the selected model
     * of several parents (one per foreign key).
     *
     * @return void
     */
    protected function addOneOfManyKeySelect(): void
    {
        $keyColumn = $this->qualifySubSelectColumn(self::ONE_OF_MANY_KEY);
        $selectedColumns = $this->query->getQuery()->columns;

        if ($selectedColumns === null) {
            $this->query->select([$this->related->qualifyColumn('*')]);
        }

        if ($selectedColumns === null || ! in_array($keyColumn, $selectedColumns, true)) {
            $this->query->addSelect($keyColumn);
        }
    }

    /**
     * Remove the matched foreign key from the attributes of the result and remember it for matching.
     *
     * @param  TRelatedModel  $result
     * @return void
     */
    protected function extractOneOfManyKey(Model $result): void
    {
        $attributes = $result->getAttributes();

        if (array_key_exists(self::ONE_OF_MANY_KEY, $attributes)) {
            $this->oneOfManyKeys ??= new WeakMap();
            $this->oneOfManyKeys[$result] = $attributes[self::ONE_OF_MANY_KEY];
            unset($attributes[self::ONE_OF_MANY_KEY]);
            $result->setRawAttributes($attributes, true);
        }
    }

    /**
     * Set the base constraints on the relation query.
     * With ofMany() the constraints are added to the per foreign key queries of the one of many subqueries.
     *
     * @return void
     */
    public function addConstraints(): void
    {
        if (! $this->isOneOfMany) {
            parent::addConstraints();

            return;
        }

        if (static::$constraints) {
            foreach ($this->oneOfManyForeignKeyQueries as $foreignKeyQuery) {
                $foreignKeyQuery['query']->where($foreignKeyQuery['foreignKey'], '=', $this->getParentKey());
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     * With ofMany() the constraints are added to the per foreign key queries of the one of many subqueries.
     *
     * @param  array<int, TDeclaringModel>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        if (! $this->isOneOfMany) {
            parent::addEagerConstraints($models);

            return;
        }

        $whereIn = $this->whereInMethod($this->parent, $this->localKey);
        $keys = $this->getKeys($models, $this->localKey);

        foreach ($this->oneOfManyForeignKeyQueries as $foreignKeyQuery) {
            $foreignKeyQuery['query']->{$whereIn}($foreignKeyQuery['foreignKey'], $keys);
        }
    }

    /**
     * Add the constraints for an internal relationship existence query.
     * With ofMany() only the selected model of each parent is considered.
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  Builder<TDeclaringModel>  $parentQuery
     * @param  array|mixed  $columns
     * @return Builder<TRelatedModel>
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query = parent::getRelationExistenceQuery($query, $parentQuery, $columns);

        if ($this->isOneOfMany) {
            $this->mergeOneOfManyJoinsTo($query);

            $query->whereColumn(
                $this->qualifySubSelectColumn(self::ONE_OF_MANY_KEY),
                '=',
                $this->getQualifiedParentKeyName()
            );
        }

        return $query;
    }

    /**
     * Indicate that the relation is a single result of a larger one-to-many relationship.
     * Info: From CanBeOneOfMany trait.
     *
     * @param  string|array<string, string>  $column
     * @param  string|Closure(Builder<TRelatedModel>): mixed  $aggregate
     * @param  string|null  $relation
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function ofMany($column = 'id', $aggregate = 'MAX', $relation = null)
    {
        $this->isOneOfMany = true;

        $this->relationName = $relation ?: $this->getDefaultOneOfManyJoinAlias(
            $this->guessRelationship()
        );

        $keyName = $this->related->getKeyName();

        $closure = $aggregate instanceof Closure ? $aggregate : null;
        $defaultAggregate = $aggregate instanceof Closure ? 'MAX' : $aggregate;

        $columns = is_string($column) ? [
            $column => $defaultAggregate,
            $keyName => $defaultAggregate,
        ] : $column;

        if (! array_key_exists($keyName, $columns)) {
            $columns[$keyName] = 'MAX';
        }

        $previous = null;
        $lastColumn = array_key_last($columns);

        foreach ($columns as $currentColumn => $currentAggregate) {
            if (! in_array(strtolower($currentAggregate), ['min', 'max'], true)) {
                throw new InvalidArgumentException("Invalid aggregate [{$currentAggregate}] used within ofMany relation. Available aggregates: MIN, MAX");
            }

            $onColumns = array_merge([$currentColumn], $previous['columns'] ?? []);

            $subQuery = $this->newOneOfManySubQuery($onColumns, $currentAggregate);

            if ($previous !== null) {
                $this->addOneOfManyJoinSubQuery($subQuery, $previous['subQuery'], $previous['columns'], true);
            }

            if ($closure !== null) {
                $closure($subQuery);
            }

            if ($previous === null) {
                $this->oneOfManySubQuery = $subQuery;
            }

            if ($currentColumn === $lastColumn) {
                $this->addOneOfManyJoinSubQuery($this->query, $subQuery, $onColumns, false);
            }

            $previous = [
                'subQuery' => $subQuery,
                'columns' => $onColumns,
            ];
        }

        $this->addConstraints();

        $selectedColumns = $this->query->getQuery()->columns;

        if ($selectedColumns === null || $selectedColumns === ['*']) {
            $this->query->select([$this->related->qualifyColumn('*')]);
        }

        return $this;
    }

    /**
     * Indicate that the relation is the latest single result of a larger one-to-many relationship.
     * Info: From CanBeOneOfMany trait.
     *
     * @param  string|string[]  $column
     * @param  string|null  $relation
     * @return $this
     */
    public function latestOfMany($column = 'id', $relation = null)
    {
        return $this->ofMany(array_fill_keys(is_array($column) ? $column : [$column], 'MAX'), 'MAX', $relation);
    }

    /**
     * Indicate that the relation is the oldest single result of a larger one-to-many relationship.
     * Info: From CanBeOneOfMany trait.
     *
     * @param  string|string[]  $column
     * @param  string|null  $relation
     * @return $this
     */
    public function oldestOfMany($column = 'id', $relation = null)
    {
        return $this->ofMany(array_fill_keys(is_array($column) ? $column : [$column], 'MIN'), 'MIN', $relation);
    }

    /**
     * Determine whether the relationship is a one-of-many relationship.
     * Info: From CanBeOneOfMany trait.
     *
     * @return bool
     */
    public function isOneOfMany()
    {
        return $this->isOneOfMany;
    }

    /**
     * Get the name of the relationship.
     * Info: From CanBeOneOfMany trait.
     *
     * @return string
     */
    public function getRelationName()
    {
        return $this->relationName;
    }

    /**
     * Get the first one of many subquery.
     * Info: From CanBeOneOfMany trait.
     *
     * @return Builder<TRelatedModel>|null
     */
    public function getOneOfManySubQuery()
    {
        return $this->oneOfManySubQuery;
    }

    /**
     * Get the qualified column name for the one-of-many relationship using the subselect join query's alias.
     * Info: From CanBeOneOfMany trait.
     *
     * @param  string  $column
     * @return string
     */
    public function qualifySubSelectColumn($column)
    {
        $segments = explode('.', $column);

        return $this->getRelationName() . '.' . end($segments);
    }

    /**
     * Get a new one of many subquery that groups the related models by the matched foreign key.
     * The subquery selects from the union of one query per foreign key (aliased as the related table),
     * where each query selects the value of its foreign key as ONE_OF_MANY_KEY column.
     * Note: Similar to newOneOfManySubQuery of CanBeOneOfMany trait.
     *
     * @param  string[]  $columns
     * @param  string  $aggregate
     * @return Builder<TRelatedModel>
     */
    protected function newOneOfManySubQuery(array $columns, string $aggregate): Builder
    {
        $table = $this->related->getTable();

        $foreignKeyQueries = [];
        foreach ($this->foreignKeys as $foreignKey) {
            $foreignKeyQuery = $this->newRelatedQuery()
                ->withoutGlobalScopes($this->removedScopes())
                ->select([$this->related->qualifyColumn('*'), $foreignKey . ' as ' . self::ONE_OF_MANY_KEY])
                ->whereNotNull($foreignKey);

            $foreignKeyQueries[] = $foreignKeyQuery;
            $this->oneOfManyForeignKeyQueries[] = [
                'query' => $foreignKeyQuery,
                'foreignKey' => $foreignKey,
            ];
        }

        // Global scopes are already applied to the per foreign key queries.
        $subQuery = $this->newRelatedQuery()->withoutGlobalScopes();

        // The union is built right before the query is executed, so that constraints that are added later
        // (for example eager loading constraints) are included.
        $subQuery->beforeQuery(function (QueryBuilder $query) use ($foreignKeyQueries, $table): void {
            $union = null;
            foreach ($foreignKeyQueries as $foreignKeyQuery) {
                if ($union === null) {
                    $union = $foreignKeyQuery->toBase();
                } else {
                    $union->unionAll($foreignKeyQuery->toBase());
                }
            }

            if ($union !== null) {
                $query->fromSub($union, $table);
            }
        });

        $subQuery->groupBy($this->related->qualifyColumn(self::ONE_OF_MANY_KEY));

        foreach ($columns as $key => $column) {
            $subQuery->addSelect(new AggregateColumnExpression(
                $key === 0 ? $aggregate : 'min',
                $this->related->qualifyColumn($column),
                $column . '_aggregate'
            ));
        }

        $subQuery->addSelect($this->related->qualifyColumn(self::ONE_OF_MANY_KEY));

        return $subQuery;
    }

    /**
     * Add the join subquery to the given query on the given columns.
     * Note: Similar to addOneOfManyJoinSubQuery of CanBeOneOfMany trait.
     *
     * @param  Builder<TRelatedModel>  $parent
     * @param  Builder<TRelatedModel>  $subQuery
     * @param  string[]  $on
     * @param  bool  $joinOnKey  Whether the parent query also has the ONE_OF_MANY_KEY column (true for subqueries)
     * @return void
     */
    protected function addOneOfManyJoinSubQuery(Builder $parent, Builder $subQuery, array $on, bool $joinOnKey): void
    {
        $relationName = $this->relationName;

        $parent->beforeQuery(function (QueryBuilder $query) use ($subQuery, $on, $joinOnKey, $relationName): void {
            $subQuery->applyBeforeQueryCallbacks();

            $query->joinSub($subQuery, $relationName, function (JoinClause $join) use ($on, $joinOnKey): void {
                foreach ($on as $onColumn) {
                    $join->on(
                        $this->qualifySubSelectColumn($onColumn . '_aggregate'),
                        '=',
                        $this->related->qualifyColumn($onColumn)
                    );
                }

                if ($joinOnKey) {
                    $join->on(
                        $this->qualifySubSelectColumn(self::ONE_OF_MANY_KEY),
                        '=',
                        $this->related->qualifyColumn(self::ONE_OF_MANY_KEY)
                    );
                }
            });
        });
    }

    /**
     * Merge the relationship query joins to the given query builder.
     * Info: From CanBeOneOfMany trait.
     *
     * @param  Builder<TRelatedModel>  $query
     * @return void
     */
    protected function mergeOneOfManyJoinsTo(Builder $query): void
    {
        $query->getQuery()->beforeQueryCallbacks = $this->query->getQuery()->beforeQueryCallbacks;

        $query->applyBeforeQueryCallbacks();
    }

    /**
     * Build the model dictionary of one of many results keyed by the matched foreign key.
     *
     * @param  Collection<int, TRelatedModel>  $results
     * @return array<array-key, list<TRelatedModel>>
     */
    protected function buildOneOfManyDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $key = $this->oneOfManyKeys !== null && isset($this->oneOfManyKeys[$result])
                ? $this->oneOfManyKeys[$result]
                : null;
            if (is_int($key) || is_string($key)) {
                $dictionary[$key][] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * Get a new query for the related model.
     *
     * @return Builder<TRelatedModel>
     */
    protected function newRelatedQuery(): Builder
    {
        return (clone $this->related)->newQuery();
    }

    /**
     * Get the default alias for the one of many inner join clause.
     * Info: From CanBeOneOfMany trait.
     *
     * @param  string  $relation
     * @return string
     */
    protected function getDefaultOneOfManyJoinAlias($relation)
    {
        return $relation === $this->related->getTable()
            ? $relation . '_of_many'
            : $relation;
    }

    /**
     * Guess the relationship's name via backtrace.
     * Info: From CanBeOneOfMany trait.
     *
     * @return string
     */
    protected function guessRelationship()
    {
        $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'];

        return str_contains($relation, '{closure') ? $this->related->getTable() : $relation;
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
