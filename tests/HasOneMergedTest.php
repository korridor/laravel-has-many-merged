<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Korridor\LaravelHasManyMerged\HasManyMerged;
use Korridor\LaravelHasManyMerged\Tests\Models\Message;
use Korridor\LaravelHasManyMerged\Tests\Models\User;

class HasOneMergedTest extends TestCase
{
    private function createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages(): void
    {
        User::query()->create([
            'id' => 11,
            'other_unique_id' => 1,
            'name' => 'Tester 1',
        ]);
        User::query()->create([
            'id' => 12,
            'other_unique_id' => 2,
            'name' => 'Tester 2',
        ]);

        Message::query()->create([
            'id' => 1,
            'content' => 'A - This is a message!',
            'sender_user_id' => 1,
            'receiver_user_id' => 1,
            'content_integer' => 1,
            'created_at' => Carbon::now()->subMinutes(4),
        ]);
        Message::query()->create([
            'id' => 2,
            'content' => 'B - This is a message!',
            'sender_user_id' => 1,
            'receiver_user_id' => 2,
            'content_integer' => 1,
            'created_at' => Carbon::now()->subMinutes(3),
        ]);
        Message::query()->create([
            'id' => 3,
            'content' => 'C - This is a message!',
            'sender_user_id' => 2,
            'receiver_user_id' => 1,
            'content_integer' => 1,
            'created_at' => Carbon::now()->subMinutes(2),
        ]);
        Message::query()->create([
            'id' => 4,
            'content' => 'D - This is a message!',
            'sender_user_id' => 2,
            'receiver_user_id' => 2,
            'content_integer' => 2,
            'created_at' => Carbon::now()->subMinutes(1),
        ]);
    }

    public function testHasOneMergedWithTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessagesWithLazyLoading(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $this->db::connection()->enableQueryLog();
        $user1 = User::find(11);
        $user2 = User::find(12);
        $latestMessageUser1 = $user1->latestMessage;
        $oldestMessageUser1 = $user1->oldestMessage;
        $latestMessageUser2 = $user2->latestMessage;
        $oldestMessageUser2 = $user2->oldestMessage;
        $queries = $this->db::getQueryLog();
        $this->db::connection()->disableQueryLog();
        fwrite(STDERR, print_r($queries, true));

        // Assert
        $this->assertEquals(6, count($queries));
        $this->assertSame(3, $latestMessageUser1->id);
        $this->assertSame(4, $latestMessageUser2->id);
        $this->assertSame(1, $oldestMessageUser1->id);
        $this->assertSame(2, $oldestMessageUser2->id);
    }

    public function testHasOneMergedWithTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessagesWithEagerLoading(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $this->db::connection()->enableQueryLog();
        $users = User::with(['latestMessage', 'oldestMessage'])->get();
        $user1 = $users->firstWhere('id', 11);
        $user2 = $users->firstWhere('id', 12);
        $latestMessageUser1 = $user1->latestMessage;
        $oldestMessageUser1 = $user1->oldestMessage;
        $latestMessageUser2 = $user2->latestMessage;
        $oldestMessageUser2 = $user2->oldestMessage;
        $queries = $this->db::getQueryLog();
        $this->db::connection()->disableQueryLog();
        fwrite(STDERR, print_r($queries, true));

        // Assert
        $this->assertEquals(3, count($queries));
        $this->assertSame(3, $latestMessageUser1->id);
        $this->assertSame(4, $latestMessageUser2->id);
        $this->assertSame(1, $oldestMessageUser1->id);
        $this->assertSame(2, $oldestMessageUser2->id);
    }
}
