<?php

namespace Korridor\LaravelHasManyMerged\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Korridor\LaravelHasManyMerged\HasManyMerged;
use Korridor\LaravelHasManyMerged\HasManyMergedRelation;

class User extends Model
{
    use HasManyMergedRelation;

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
     * @return HasManyMerged|Message
     */
    public function messages()
    {
        return $this->hasManyMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id');
    }

    /**
     * @return HasMany|Message
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_user_id', 'other_unique_id');
    }

    /**
     * @return HasMany|Message
     */
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_user_id', 'other_unique_id');
    }
}
