<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * Select expression of an aggregated column, for example `max("messages"."id") as "id_aggregate"`.
 * The column and alias are wrapped by the grammar of the query when it is compiled.
 *
 * @internal
 */
class AggregateColumnExpression implements Expression
{
    /**
     * @param  string  $aggregate  Aggregate function (min or max)
     * @param  string  $column  Qualified column name
     * @param  string  $alias  Alias of the aggregated column
     */
    public function __construct(
        protected string $aggregate,
        protected string $column,
        protected string $alias
    ) {
    }

    /**
     * Get the value of the expression.
     *
     * @param  Grammar  $grammar
     * @return string
     */
    public function getValue(Grammar $grammar)
    {
        return $this->aggregate . '(' . $grammar->wrap($this->column) . ') as ' . $grammar->wrap($this->alias);
    }
}
