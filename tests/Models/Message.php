<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Korridor\LaravelHasManyMerged\HasManyMergedRelation;

/**
 * @property int $id
 */
class Message extends Model
{
    use HasManyMergedRelation;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'id',
        'content',
        'content_integer',
        'sender_user_id',
        'receiver_user_id',
    ];

    /**
     * @return BelongsTo<User, Message>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * @return BelongsTo<User, Message>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }
}
