<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait HasManyMergedRelation
{
    /**
     * @template TRelatedModel of Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string[]|null  $foreignKeys
     * @param  string|null  $localKey
     * @return HasManyMerged<TRelatedModel, $this>
     */
    public function hasManyMerged(string $related, ?array $foreignKeys = null, ?string $localKey = null): HasManyMerged
    {
        if ($foreignKeys === null || $foreignKeys === []) {
            throw new InvalidArgumentException('hasManyMerged() requires at least one foreign key.');
        }

        $instance = new $related();

        $localKey = $localKey ?: $this->getKeyName();

        $foreignKeys = array_map(function (string $foreignKey) use ($instance): string {
            return $instance->getTable() . '.' . $foreignKey;
        }, $foreignKeys);

        /** @var \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query */
        $query = $instance->newQuery();

        return new HasManyMerged($query, $this, $foreignKeys, $localKey);
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    abstract public function getKeyName();
}
