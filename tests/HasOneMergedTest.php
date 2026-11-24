<?php

declare(strict_types=1);

namespace Korridor\LaravelHasManyMerged\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Korridor\LaravelHasManyMerged\HasOneMerged;
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

        // The ids are deliberately not in chronological order, so the tests notice if the ordering is ignored.
        Message::query()->create([
            'id' => 1,
            'content' => 'A - This is a message!',
            'sender_user_id' => 1,
            'receiver_user_id' => 2,
            'content_integer' => 1,
            'created_at' => Carbon::now()->subMinutes(2),
        ]);
        Message::query()->create([
            'id' => 2,
            'content' => 'B - This is a message!',
            'sender_user_id' => 1,
            'receiver_user_id' => 1,
            'content_integer' => 1,
            'created_at' => Carbon::now()->subMinutes(4),
        ]);
        Message::query()->create([
            'id' => 3,
            'content' => 'C - This is a message!',
            'sender_user_id' => 2,
            'receiver_user_id' => 2,
            'content_integer' => 2,
            'created_at' => Carbon::now()->subMinutes(1),
        ]);
        Message::query()->create([
            'id' => 4,
            'content' => 'D - This is a message!',
            'sender_user_id' => 2,
            'receiver_user_id' => 1,
            'content_integer' => 1,
            'created_at' => Carbon::now()->subMinutes(3),
        ]);
    }

    public function testHasOneMergedWithLazyLoading(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $this->db::connection()->enableQueryLog();
        $user1 = User::query()->findOrFail(11);
        $user2 = User::query()->findOrFail(12);
        $latestMessageUser1 = $user1->latestMessage;
        $oldestMessageUser1 = $user1->oldestMessage;
        $latestMessageUser2 = $user2->latestMessage;
        $oldestMessageUser2 = $user2->oldestMessage;
        $queries = $this->db::connection()->getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertCount(6, $queries);
        $this->assertInstanceOf(Message::class, $latestMessageUser1);
        $this->assertInstanceOf(Message::class, $oldestMessageUser1);
        $this->assertInstanceOf(Message::class, $latestMessageUser2);
        $this->assertInstanceOf(Message::class, $oldestMessageUser2);
        $this->assertSame(1, $latestMessageUser1->id);
        $this->assertSame(2, $oldestMessageUser1->id);
        $this->assertSame(3, $latestMessageUser2->id);
        $this->assertSame(4, $oldestMessageUser2->id);
    }

    public function testHasOneMergedWithEagerLoading(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $this->db::connection()->enableQueryLog();
        $users = User::with(['latestMessage', 'oldestMessage'])->get();
        $user1 = $users->firstOrFail('id', 11);
        $user2 = $users->firstOrFail('id', 12);
        $latestMessageUser1 = $user1->latestMessage;
        $oldestMessageUser1 = $user1->oldestMessage;
        $latestMessageUser2 = $user2->latestMessage;
        $oldestMessageUser2 = $user2->oldestMessage;
        $queries = $this->db::connection()->getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertCount(3, $queries);
        $this->assertSame(1, $latestMessageUser1?->id);
        $this->assertSame(2, $oldestMessageUser1?->id);
        $this->assertSame(3, $latestMessageUser2?->id);
        $this->assertSame(4, $oldestMessageUser2?->id);
    }

    public function testHasOneMergedWithLazyEagerLoading(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        $users = User::query()->get();

        // Act
        $users->load('latestMessage');

        // Assert
        $this->assertSame(1, $users->firstOrFail('id', 11)->latestMessage?->id);
        $this->assertSame(3, $users->firstOrFail('id', 12)->latestMessage?->id);
    }

    public function testHasOneMergedWithConstrainedEagerLoading(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $users = User::with([
            'latestMessage' => function (HasOneMerged $query): void {
                $query->where('content_integer', '=', 1);
            },
        ])->get();

        // Assert
        $this->assertSame(1, $users->firstOrFail('id', 11)->latestMessage?->id);
        $this->assertSame(1, $users->firstOrFail('id', 12)->latestMessage?->id);
    }

    public function testHasOneMergedReturnsNullIfThereIsNoRelatedModel(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        User::query()->create([
            'id' => 13,
            'other_unique_id' => 3,
            'name' => 'Tester 3',
        ]);

        // Act
        $lazyLoadedUser = User::query()->findOrFail(13);
        $eagerLoadedUser = User::with(['latestMessage'])->findOrFail(13);

        // Assert
        $this->assertNull($lazyLoadedUser->latestMessage);
        $this->assertTrue($eagerLoadedUser->relationLoaded('latestMessage'));
        $this->assertNull($eagerLoadedUser->latestMessage);
    }

    public function testHasOneMergedReturnsNullWithoutQueryIfParentKeyIsNull(): void
    {
        // Arrange
        $user = new User();

        // Act
        $this->db::connection()->enableQueryLog();
        $latestMessage = $user->latestMessage;
        $queries = $this->db::connection()->getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertNull($latestMessage);
        $this->assertCount(0, $queries);
    }

    public function testHasOneMergedWithWhereHas(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        User::query()->create([
            'id' => 13,
            'other_unique_id' => 3,
            'name' => 'Tester 3',
        ]);

        // Act
        $users = User::query()->whereHas('latestMessage', function (Builder $query): void {
            $query->where('content_integer', '=', 2);
        })->get();

        // Assert
        $this->assertSame([12], $users->pluck('id')->all());
    }

    public function testHasOneMergedWhereHasMatchesAnyRelatedModelLikeHasOne(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        // User 12 has older messages with content_integer 1, but the latest message (id 3) has content_integer 2.
        // Like Laravel's hasOne without ofMany, whereHas() ignores the ordering and matches any related model.
        $usersWithLatestMessage = User::query()->whereHas('latestMessage', function (Builder $query): void {
            $query->where('content_integer', '=', 1);
        })->orderBy('id')->get();
        $usersWithoutLatestMessage = User::query()->whereDoesntHave('latestMessage', function (Builder $query): void {
            $query->where('content_integer', '=', 1);
        })->get();

        // Assert
        $this->assertSame([11, 12], $usersWithLatestMessage->pluck('id')->all());
        $this->assertSame([], $usersWithoutLatestMessage->pluck('id')->all());
    }

    public function testHasOneMergedWithCountCountsAllRelatedModelsLikeHasOne(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        User::query()->create([
            'id' => 13,
            'other_unique_id' => 3,
            'name' => 'Tester 3',
        ]);

        // Act
        $users = User::query()->withCount('latestMessage')->orderBy('id')->get();
        $usersWithMessage = User::query()->has('latestMessage')->orderBy('id')->get();

        // Assert
        // Like Laravel's hasOne without ofMany, withCount() counts every related model.
        $this->assertEquals([3, 3, 0], $users->pluck('latest_message_count')->all());
        $this->assertSame([11, 12], $usersWithMessage->pluck('id')->all());
    }

    public function testParentsCanBeOrderedByTheirLatestRelatedModel(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $users = User::query()
            ->with('latestMessage')
            ->get()
            ->sortByDesc(fn (User $user) => $user->latestMessage?->created_at)
            ->values();

        // Assert
        $this->assertSame([12, 11], $users->pluck('id')->all());
    }

    public function testHasOneMergedWithDefaultReturnsRelatedModelIfItExists(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $lazyLoadedUser = User::query()->findOrFail(11);
        $eagerLoadedUser = User::with(['latestMessageWithDefault'])->findOrFail(11);

        // Assert
        $this->assertSame(1, $lazyLoadedUser->latestMessageWithDefault->id);
        $this->assertTrue($lazyLoadedUser->latestMessageWithDefault->exists);
        $this->assertSame(1, $eagerLoadedUser->latestMessageWithDefault->id);
    }

    public function testHasOneMergedWithDefaultReturnsDefaultModelIfThereIsNoRelatedModel(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        User::query()->create([
            'id' => 13,
            'other_unique_id' => 3,
            'name' => 'Tester 3',
        ]);

        // Act
        $lazyLoadedUser = User::query()->findOrFail(13);
        $eagerLoadedUser = User::with(['latestMessageWithDefault'])->findOrFail(13);
        $lazyDefault = $lazyLoadedUser->latestMessageWithDefault;
        $eagerDefault = $eagerLoadedUser->latestMessageWithDefault;

        // Assert
        foreach ([$lazyDefault, $eagerDefault] as $default) {
            $this->assertInstanceOf(Message::class, $default);
            $this->assertFalse($default->exists);
            $this->assertSame('No messages yet', $default->getAttribute('content'));
            // It is ambiguous which foreign key should be set, so none is set.
            $this->assertNull($default->getAttribute('sender_user_id'));
            $this->assertNull($default->getAttribute('receiver_user_id'));
        }
    }

    public function testHasOneMergedWithDefaultReturnsDefaultModelWithoutQueryIfParentKeyIsNull(): void
    {
        // Arrange
        $user = new User();

        // Act
        $this->db::connection()->enableQueryLog();
        $default = $user->latestMessageWithDefault;
        $queries = $this->db::connection()->getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertInstanceOf(Message::class, $default);
        $this->assertFalse($default->exists);
        $this->assertCount(0, $queries);
    }

    public function testHasOneMergedWithDefaultAcceptsClosure(): void
    {
        // Arrange
        User::query()->create([
            'id' => 13,
            'other_unique_id' => 3,
            'name' => 'Tester 3',
        ]);
        $user = User::query()->findOrFail(13);

        // Act
        $default = $user->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id')
            ->withDefault(function (Message $message, User $parent): void {
                $message->setAttribute('content', 'No messages for ' . $parent->name);
            })
            ->getResults();

        // Assert
        $this->assertInstanceOf(Message::class, $default);
        $this->assertSame('No messages for Tester 3', $default->getAttribute('content'));
    }

    public function testHasOneMergedWithoutForeignKeysThrowsException(): void
    {
        // Arrange
        $user = new User();

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $user->hasOneMerged(Message::class, [], 'other_unique_id');
    }

    public function testHasOneMergedConstructorWithoutForeignKeysThrowsException(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HasOneMerged requires at least one foreign key.');

        // Act
        new HasOneMerged(Message::query(), new User(), [], 'other_unique_id');
    }
}
