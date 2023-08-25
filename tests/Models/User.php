<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Korridor\LaravelHasManyMerged\HasManyMerged;
use Korridor\LaravelHasManyMerged\HasManyMergedRelation;
use Korridor\LaravelHasManyMerged\HasOneMerged;
use Korridor\LaravelHasManyMerged\HasOneMergedRelation;

/**
 * @property int $id
 * @property int $other_unique_id
 * @property string $name
 * @property int $messages_sum_content_integer
 * @property ?Message $latestMessage
 * @property ?Message $oldestMessage
 */
class User extends Model
{
    use HasManyMergedRelation;
    use HasOneMergedRelation;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'id',
        'other_unique_id',
        'name',
    ];

    /**
     * @return HasManyMerged<Message>
     */
    public function messages(): HasManyMerged
    {
        return $this->hasManyMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id');
    }

    /**
     * @return HasOneMerged<Message>
     */
    public function latestMessage(): HasOneMerged
    {
        return $this->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id')
            ->latest();
    }

    /**
     * @return HasOneMerged<Message>
     */
    public function oldestMessage(): HasOneMerged
    {
        return $this->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id')
            ->oldest();
    }

    /**
     * @return HasMany<Message>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_user_id', 'other_unique_id');
    }

    /**
     * @return HasMany<Message>
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_user_id', 'other_unique_id');
    }
}
