<?php

namespace Korridor\LaravelHasManyMerged\Tests;

use Korridor\LaravelHasManyMerged\HasManyMerged;
use Korridor\LaravelHasManyMerged\Tests\Models\Message;
use Korridor\LaravelHasManyMerged\Tests\Models\User;

class HasManyMergedTest extends TestCase
{
    private function createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages()
    {
        User::create([
            'id' => 11,
            'other_unique_id' => 1,
            'name' => 'Tester 1',
        ]);
        User::create([
            'id' => 12,
            'other_unique_id' => 2,
            'name' => 'Tester 2',
        ]);

        Message::create([
            'id' => 1,
            'content' => 'A - This is a message!',
            'sender_user_id' => 1,
            'receiver_user_id' => 1,
        ]);
        Message::create([
            'id' => 2,
            'content' => 'B - This is a message!',
            'sender_user_id' => 1,
            'receiver_user_id' => 2,
        ]);
        Message::create([
            'id' => 3,
            'content' => 'C - This is a message!',
            'sender_user_id' => 2,
            'receiver_user_id' => 1,
        ]);
        Message::create([
            'id' => 4,
            'content' => 'D - This is a message!',
            'sender_user_id' => 2,
            'receiver_user_id' => 2,
        ]);
    }

    public function testHasManyMergedWithTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessagesWithLazyLoading()
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $this->db::connection()->enableQueryLog();
        $user1 = User::find(11);
        $user2 = User::find(12);
        $messagesOfUser1 = $user1->messages;
        $messagesOfUser2 = $user2->messages;
        $queries = $this->db::getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertEquals(4, count($queries));
        $this->assertEquals(3, $messagesOfUser1->count());
        $this->assertEquals(3, $messagesOfUser2->count());
        $this->assertEquals(2, $user1->receivedMessages()->count());
        $this->assertEquals(2, $user1->sentMessages()->count());
        $this->assertEquals(2, $user2->receivedMessages()->count());
        $this->assertEquals(2, $user2->sentMessages()->count());
    }

    public function testHasManyMergedWithTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessagesWithEagerLoading()
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $this->db::connection()->enableQueryLog();
        $users = User::with(['messages'])->get();
        $user1 = $users->firstWhere('id', 11);
        $user2 = $users->firstWhere('id', 12);
        $messagesOfUser1 = $user1->messages;
        $messagesOfUser2 = $user2->messages;
        $queries = $this->db::getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertEquals(2, count($queries));
        $this->assertEquals(3, $messagesOfUser1->count());
        $this->assertEquals(3, $messagesOfUser2->count());
        $this->assertEquals(2, $user1->receivedMessages()->count());
        $this->assertEquals(2, $user1->sentMessages()->count());
        $this->assertEquals(2, $user2->receivedMessages()->count());
        $this->assertEquals(2, $user2->sentMessages()->count());
    }

    public function testHasManyMergedWithTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessagesWithLazyEagerLoading()
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $this->db::connection()->enableQueryLog();
        $users = User::all();
        $users->load(['messages']);
        $user1 = $users->firstWhere('id', 11);
        $user2 = $users->firstWhere('id', 12);
        $messagesOfUser1 = $user1->messages;
        $messagesOfUser2 = $user2->messages;
        $queries = $this->db::getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertEquals(2, count($queries));
        $this->assertEquals(3, $messagesOfUser1->count());
        $this->assertEquals(3, $messagesOfUser2->count());
        $this->assertEquals(2, $user1->receivedMessages()->count());
        $this->assertEquals(2, $user1->sentMessages()->count());
        $this->assertEquals(2, $user2->receivedMessages()->count());
        $this->assertEquals(2, $user2->sentMessages()->count());
    }

    public function testHasManyMergedWithTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessagesWithConstrainedEagerLoading()
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $this->db::connection()->enableQueryLog();
        $users = User::with([
            'messages' => function (HasManyMerged $builder) {
                $builder->where('content', 'like', 'A -%');
            },
        ])->get();
        $user1 = $users->firstWhere('id', 11);
        $user2 = $users->firstWhere('id', 12);
        $messagesOfUser1 = $user1->messages;
        $messagesOfUser2 = $user2->messages;
        $queries = $this->db::getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertEquals(2, count($queries));
        $this->assertEquals(1, $messagesOfUser1->count());
        $this->assertEquals(0, $messagesOfUser2->count());
        $this->assertEquals(2, $user1->receivedMessages()->count());
        $this->assertEquals(2, $user1->sentMessages()->count());
        $this->assertEquals(2, $user2->receivedMessages()->count());
        $this->assertEquals(2, $user2->sentMessages()->count());
    }

    public function testHasManyMergedWithTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessagesWithEagerLoadingAndOrderCheck()
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $this->db::connection()->enableQueryLog();
        $users = User::with([
            'messages' => function (HasManyMerged $builder) {
                $builder->orderBy('id', 'desc');
            },
        ])->get();
        $user1 = $users->firstWhere('id', 11);
        $user2 = $users->firstWhere('id', 12);
        $messagesOfUser1 = $user1->messages;
        $messagesOfUser2 = $user2->messages;
        $queries = $this->db::getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertEquals(2, count($queries));
        $this->assertEquals(3, $messagesOfUser1->count());
        $this->assertEquals(3, $messagesOfUser2->count());
        $this->assertEquals([3, 2, 1], $messagesOfUser1->pluck('id')->toArray());
        $this->assertEquals([4, 3, 2], $messagesOfUser2->pluck('id')->toArray());
        $this->assertEquals(2, $user1->receivedMessages()->count());
        $this->assertEquals(2, $user1->sentMessages()->count());
        $this->assertEquals(2, $user2->receivedMessages()->count());
        $this->assertEquals(2, $user2->sentMessages()->count());
    }
}
