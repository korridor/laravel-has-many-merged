<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged;

trait HasManyMergedRelation
{
    /**
     * @param  string  $related
     * @param  string[]|null  $foreignKeys
     * @param  string|null  $localKey
     * @return HasManyMerged
     */
    public function hasManyMerged(string $related, ?array $foreignKeys = null, ?string $localKey = null): HasManyMerged
    {
        $instance = new $related();

        $localKey = $localKey ?: $this->getKeyName();

        $foreignKeys = array_map(function ($foreignKey) use ($instance) {
            return $instance->getTable() . '.' . $foreignKey;
        }, $foreignKeys);

        return new HasManyMerged($instance->newQuery(), $this, $foreignKeys, $localKey);
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    abstract public function getKeyName();
}
