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

    public function testHasOneMergedWithEagerLoadingSelectsTheSameModelForSeveralParents(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        // The newest message is the latest message of both its sender and its receiver.
        Message::query()->create([
            'id' => 5,
            'content' => 'E - This is a message!',
            'sender_user_id' => 1,
            'receiver_user_id' => 2,
            'content_integer' => 1,
            'created_at' => Carbon::now(),
        ]);

        // Act
        $users = User::with(['latestMessage'])->get();

        // Assert
        $this->assertSame(5, $users->firstOrFail('id', 11)->latestMessage?->id);
        $this->assertSame(5, $users->firstOrFail('id', 12)->latestMessage?->id);
    }

    public function testHasOneMergedWithNullForeignKeysThrowsException(): void
    {
        // Arrange
        $user = new User();

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('hasOneMerged() requires at least one foreign key.');

        // Act
        $user->hasOneMerged(Message::class, null, 'other_unique_id');
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

    public function testOfManyWithLazyLoading(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        $user1 = User::query()->findOrFail(11);
        $user2 = User::query()->findOrFail(12);

        // Act
        $this->db::connection()->enableQueryLog();
        $results = [
            'latestMessageOfMany' => [$user1->latestMessageOfMany?->id, $user2->latestMessageOfMany?->id],
            'oldestMessageOfMany' => [$user1->oldestMessageOfMany?->id, $user2->oldestMessageOfMany?->id],
            'latestCreatedMessageOfMany' => [$user1->latestCreatedMessageOfMany?->id, $user2->latestCreatedMessageOfMany?->id],
            'oldestCreatedMessageOfMany' => [$user1->oldestCreatedMessageOfMany?->id, $user2->oldestCreatedMessageOfMany?->id],
            'latestCreatedMessageWithContentIntegerOneOfMany' => [
                $user1->latestCreatedMessageWithContentIntegerOneOfMany?->id,
                $user2->latestCreatedMessageWithContentIntegerOneOfMany?->id,
            ],
        ];
        $queries = $this->db::connection()->getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertCount(10, $queries);
        $this->assertSame([
            'latestMessageOfMany' => [4, 4],
            'oldestMessageOfMany' => [1, 1],
            'latestCreatedMessageOfMany' => [1, 3],
            'oldestCreatedMessageOfMany' => [2, 4],
            'latestCreatedMessageWithContentIntegerOneOfMany' => [1, 1],
        ], $results);
        $this->assertArrayNotHasKey(HasOneMerged::ONE_OF_MANY_KEY, $user1->latestMessageOfMany?->getAttributes() ?? []);
    }

    public function testOfManyWithEagerLoading(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        $relations = [
            'latestMessageOfMany',
            'oldestMessageOfMany',
            'latestCreatedMessageOfMany',
            'oldestCreatedMessageOfMany',
            'latestCreatedMessageWithContentIntegerOneOfMany',
        ];

        // Act
        $this->db::connection()->enableQueryLog();
        $users = User::with($relations)->orderBy('id')->get();
        $queries = $this->db::connection()->getQueryLog();
        $this->db::connection()->disableQueryLog();
        $user1 = $users->firstOrFail('id', 11);
        $user2 = $users->firstOrFail('id', 12);
        $results = [
            'latestMessageOfMany' => [$user1->latestMessageOfMany?->id, $user2->latestMessageOfMany?->id],
            'oldestMessageOfMany' => [$user1->oldestMessageOfMany?->id, $user2->oldestMessageOfMany?->id],
            'latestCreatedMessageOfMany' => [$user1->latestCreatedMessageOfMany?->id, $user2->latestCreatedMessageOfMany?->id],
            'oldestCreatedMessageOfMany' => [$user1->oldestCreatedMessageOfMany?->id, $user2->oldestCreatedMessageOfMany?->id],
            'latestCreatedMessageWithContentIntegerOneOfMany' => [
                $user1->latestCreatedMessageWithContentIntegerOneOfMany?->id,
                $user2->latestCreatedMessageWithContentIntegerOneOfMany?->id,
            ],
        ];

        // Assert
        $this->assertCount(6, $queries);
        $this->assertSame([
            'latestMessageOfMany' => [4, 4],
            'oldestMessageOfMany' => [1, 1],
            'latestCreatedMessageOfMany' => [1, 3],
            'oldestCreatedMessageOfMany' => [2, 4],
            'latestCreatedMessageWithContentIntegerOneOfMany' => [1, 1],
        ], $results);
        $this->assertArrayNotHasKey(HasOneMerged::ONE_OF_MANY_KEY, $user1->latestMessageOfMany?->getAttributes() ?? []);
    }

    public function testOfManyWithLazyEagerLoading(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        $users = User::query()->orderBy('id')->get();

        // Act
        $users->load('latestCreatedMessageOfMany');

        // Assert
        $this->assertSame([1, 3], $users->map(fn (User $user) => $user->latestCreatedMessageOfMany?->id)->all());
    }

    public function testOfManyWithConstrainedEagerLoadingOnlyFiltersTheSelectedModel(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $users = User::with([
            'latestCreatedMessageOfMany' => function (HasOneMerged $query): void {
                $query->where('content_integer', '=', 1);
            },
        ])->orderBy('id')->get();

        // Assert
        // Like Laravel's latestOfMany(), the constraint is applied to the latest model and does not select another one.
        $this->assertSame([1, null], $users->map(fn (User $user) => $user->latestCreatedMessageOfMany?->id)->all());
    }

    public function testOfManyReturnsNullIfThereIsNoRelatedModel(): void
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
        $eagerLoadedUser = User::with(['latestMessageOfMany'])->findOrFail(13);

        // Assert
        $this->assertNull($lazyLoadedUser->latestMessageOfMany);
        $this->assertTrue($eagerLoadedUser->relationLoaded('latestMessageOfMany'));
        $this->assertNull($eagerLoadedUser->latestMessageOfMany);
    }

    public function testOfManyReturnsNullWithoutQueryIfParentKeyIsNull(): void
    {
        // Arrange
        $user = new User();

        // Act
        $this->db::connection()->enableQueryLog();
        $latestMessage = $user->latestMessageOfMany;
        $queries = $this->db::connection()->getQueryLog();
        $this->db::connection()->disableQueryLog();

        // Assert
        $this->assertNull($latestMessage);
        $this->assertCount(0, $queries);
    }

    public function testOfManyUsesTheIdAsTiebreaker(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        $createdAt = Carbon::now()->addMinute();
        foreach ([6, 5] as $id) {
            Message::query()->create([
                'id' => $id,
                'content' => 'Tie',
                'sender_user_id' => 1,
                'receiver_user_id' => 1,
                'created_at' => $createdAt,
            ]);
        }

        // Act
        $lazyLoadedUser = User::query()->findOrFail(11);
        $eagerLoadedUsers = User::with(['latestCreatedMessageOfMany'])->orderBy('id')->get();

        // Assert
        $this->assertSame(6, $lazyLoadedUser->latestCreatedMessageOfMany?->id);
        $this->assertSame([6, 3], $eagerLoadedUsers->map(fn (User $user) => $user->latestCreatedMessageOfMany?->id)->all());
    }

    public function testOfManyWithWhereHasOnlyConsidersTheSelectedModel(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        User::query()->create([
            'id' => 13,
            'other_unique_id' => 3,
            'name' => 'Tester 3',
        ]);

        // Act
        $usersWithLatestContentIntegerOne = User::query()
            ->whereHas('latestCreatedMessageOfMany', function (Builder $query): void {
                $query->where('content_integer', '=', 1);
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $usersWithLatestContentIntegerTwo = User::query()
            ->whereHas('latestCreatedMessageOfMany', function (Builder $query): void {
                $query->where('content_integer', '=', 2);
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $usersWithoutLatestContentIntegerOne = User::query()
            ->whereDoesntHave('latestCreatedMessageOfMany', function (Builder $query): void {
                $query->where('content_integer', '=', 1);
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $usersWithOldestMessageFour = User::query()
            ->whereHas('oldestCreatedMessageOfMany', function (Builder $query): void {
                $query->where('messages.id', '=', 4);
            })
            ->pluck('id')
            ->all();
        $usersWithMessages = User::query()->has('latestMessageOfMany')->orderBy('id')->pluck('id')->all();

        // Assert
        $this->assertSame([11], $usersWithLatestContentIntegerOne);
        $this->assertSame([12], $usersWithLatestContentIntegerTwo);
        $this->assertSame([12, 13], $usersWithoutLatestContentIntegerOne);
        $this->assertSame([12], $usersWithOldestMessageFour);
        $this->assertSame([11, 12], $usersWithMessages);
    }

    public function testOfManyWithWithCount(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        User::query()->create([
            'id' => 13,
            'other_unique_id' => 3,
            'name' => 'Tester 3',
        ]);

        // Act
        $users = User::query()
            ->withCount([
                'latestCreatedMessageOfMany',
                'latestCreatedMessageOfMany as latest_message_with_content_integer_one_count' => function (Builder $query): void {
                    $query->where('content_integer', '=', 1);
                },
            ])
            ->orderBy('id')
            ->get();

        // Assert
        $this->assertSame([1, 1, 0], $users->pluck('latest_created_message_of_many_count')->all());
        $this->assertSame([1, 0, 0], $users->pluck('latest_message_with_content_integer_one_count')->all());
    }

    public function testOfManyWithInvalidAggregateThrowsException(): void
    {
        // Arrange
        $user = new User();

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid aggregate [avg] used within ofMany relation. Available aggregates: MIN, MAX');

        // Act
        $user->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id')->ofMany('id', 'avg');
    }

    public function testOfManyWithSelectAllInConstrainedEagerLoadingOnlySelectsTheRelatedColumns(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();

        // Act
        $users = User::with([
            'latestCreatedMessageOfMany' => function (HasOneMerged $query): void {
                $query->select('*');
            },
        ])->orderBy('id')->get();

        // Assert
        $this->assertSame([1, 3], $users->map(fn (User $user) => $user->latestCreatedMessageOfMany?->id)->all());
        $this->assertSame(
            array_keys(Message::query()->findOrFail(1)->getAttributes()),
            array_keys($users->firstOrFail()->latestCreatedMessageOfMany?->getAttributes() ?? [])
        );
    }

    public function testOfManyRelationNameAndSubQuery(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        $user = User::query()->findOrFail(12);

        // Act
        $plainRelation = $user->latestMessage();
        $guessedRelation = $user->latestMessageOfMany();
        $namedRelation = $user->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id')
            ->latestOfMany('created_at', 'custom_alias');
        $closureRelation = (function () use ($user): HasOneMerged {
            return $user->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'], 'other_unique_id')
                ->ofMany('created_at');
        })();

        // Assert
        $this->assertFalse($plainRelation->isOneOfMany());
        $this->assertNull($plainRelation->getOneOfManySubQuery());
        $this->assertTrue($guessedRelation->isOneOfMany());
        $this->assertSame('latestOfMany', $guessedRelation->getRelationName());
        $this->assertInstanceOf(Builder::class, $guessedRelation->getOneOfManySubQuery());
        $this->assertSame('custom_alias', $namedRelation->getRelationName());
        $this->assertSame(3, $namedRelation->getResults()?->id);
        $this->assertSame('messages_of_many', $closureRelation->getRelationName());
        $this->assertSame(3, $closureRelation->getResults()?->id);
    }

    public function testOfManyMatchIgnoresResultsThatWereNotLoadedByTheRelation(): void
    {
        // Arrange
        $this->createTwoUsersWereBothAreSenderOrReceiverOfTheSameFourMessages();
        $users = User::query()->orderBy('id')->get();
        $relation = $users->firstOrFail()->latestMessageOfMany();

        // Act
        $models = $relation->match(
            $relation->initRelation($users->all(), 'latestMessageOfMany'),
            Message::query()->get(),
            'latestMessageOfMany'
        );

        // Assert
        $this->assertCount(2, $models);
        foreach ($models as $model) {
            $this->assertNull($model->latestMessageOfMany);
        }
    }
}
