<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait HasOneMergedRelation
{
    /**
     * @template TRelatedModel of Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string[]|null  $foreignKeys
     * @param  string|null  $localKey
     * @return HasOneMerged<TRelatedModel, $this>
     */
    public function hasOneMerged(string $related, ?array $foreignKeys = null, ?string $localKey = null): HasOneMerged
    {
        if ($foreignKeys === null || $foreignKeys === []) {
            throw new InvalidArgumentException('hasOneMerged() requires at least one foreign key.');
        }

        $instance = new $related();

        $localKey = $localKey ?: $this->getKeyName();

        $foreignKeys = array_map(function ($foreignKey) use ($instance) {
            return $instance->getTable() . '.' . $foreignKey;
        }, $foreignKeys);

        return new HasOneMerged($instance->newQuery(), $this, $foreignKeys, $localKey);
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    abstract public function getKeyName();
}
