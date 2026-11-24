<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged\Tests\Models;

use Illuminate\Database\Eloquent\Collection;
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
 * @property int $messages_count
 * @property-read Collection<int, Message> $messages
 * @property-read Message|null $latestMessage
 * @property-read Message|null $oldestMessage
 * @property-read Message $latestMessageWithDefault
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
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'other_unique_id',
        'name',
    ];

    /**
     * @return HasManyMerged<Message, $this>
     */
    public function messages(): HasManyMerged
    {
        return $this->hasManyMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id');
    }

    /**
     * Uses the integer primary key as local key.
     *
     * @return HasManyMerged<Message, $this>
     */
    public function messagesByPrimaryKey(): HasManyMerged
    {
        return $this->hasManyMerged(Message::class, ['sender_user_id', 'receiver_user_id']);
    }

    /**
     * @return HasOneMerged<Message, $this>
     */
    public function latestMessage(): HasOneMerged
    {
        return $this->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return HasOneMerged<Message, $this>
     */
    public function oldestMessage(): HasOneMerged
    {
        return $this->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return HasOneMerged<Message, $this>
     */
    public function latestMessageWithDefault(): HasOneMerged
    {
        return $this->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->withDefault(['content' => 'No messages yet']);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_user_id', 'other_unique_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_user_id', 'other_unique_id');
    }
}
