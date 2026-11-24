# Laravel has many merged

[![Latest Version on Packagist](https://img.shields.io/packagist/v/korridor/laravel-has-many-merged?style=flat-square)](https://packagist.org/packages/korridor/laravel-has-many-merged)
[![License](https://img.shields.io/packagist/l/korridor/laravel-has-many-merged?style=flat-square)](license.md)
[![GitHub Workflow Lint](https://img.shields.io/github/actions/workflow/status/korridor/laravel-has-many-merged/lint.yml?label=lint&style=flat-square)](https://github.com/korridor/laravel-has-many-merged/actions/workflows/lint.yml)
[![GitHub Workflow Tests](https://img.shields.io/github/actions/workflow/status/korridor/laravel-has-many-merged/unittests.yml?label=tests&style=flat-square)](https://github.com/korridor/laravel-has-many-merged/actions/workflows/unittests.yml)
[![Codecov](https://img.shields.io/codecov/c/github/korridor/laravel-has-many-merged?style=flat-square)](https://codecov.io/gh/korridor/laravel-has-many-merged)

Custom relationship for Eloquent that merges/combines multiple one-to-many (hasMany) relationships into one hasManyMerged or hasOneMerged relation.
This relation fully supports lazy and eager loading.

> [!NOTE]
> Check out **solidtime - The modern Open Source Time-Tracker** at [solidtime.io](https://www.solidtime.io)

## Installation

You can install the package via composer with the following command:

```bash
composer require korridor/laravel-has-many-merged
```

If you want to use this package with an older Laravel/PHP version (Laravel 6 to 9, PHP 7.1+), please install the 0.* version.

```bash
composer require korridor/laravel-has-many-merged "^0"
```

### Requirements

This package is tested for the following Laravel versions:

- 13.* (PHP 8.3, 8.4)
- 12.* (PHP 8.2, 8.3, 8.4)
- 11.* (PHP 8.2, 8.3, 8.4)
- 10.* (PHP 8.1, 8.2, 8.3)

## Usage

In the following example there are two models User and Message.
Each message has a sender and a receiver.
The User model has two hasMany relations - one for the sent messages and the other for the received ones.

With this package you can add a relation that contains sent and received messages of a user.

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Korridor\LaravelHasManyMerged\HasManyMerged;
use Korridor\LaravelHasManyMerged\HasManyMergedRelation;

class User extends Model
{
    use HasManyMergedRelation;

    // ...

    /**
     * @return HasManyMerged<Message, $this>
     */
    public function messages(): HasManyMerged
    {
        return $this->hasManyMerged(Message::class, ['sender_user_id', 'receiver_user_id']);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_user_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_user_id');
    }
}
```

The relation can be used like any other Eloquent relation:

```php
// Lazy loading
$messages = $user->messages;

// Eager loading
$users = User::with('messages')->get();

// Constrained eager loading
$users = User::with(['messages' => function ($query) {
    $query->orderBy('created_at', 'desc');
}])->get();

// Querying the relation
$unreadMessages = $user->messages()->whereNull('read_at')->get();

// Aggregates and existence queries
$users = User::withCount('messages')->withSum('messages', 'size')->get();
$users = User::whereHas('messages', function ($query) {
    $query->where('subject', 'like', '%invoice%');
})->get();
```

A model that matches more than one of the foreign keys (for example a message a user sent to themselves) is only included once.

### Method signatures

```php
hasManyMerged(string $related, array $foreignKeys, ?string $localKey = null): HasManyMerged
hasOneMerged(string $related, array $foreignKeys, ?string $localKey = null): HasOneMerged
```

Both methods take the same parameters:

- `$related`: The class name of the related model.
- `$foreignKeys`: The columns on the related model's table that reference the parent model. At least one is required, otherwise an `InvalidArgumentException` is thrown.
- `$localKey`: The column on the parent model that the foreign keys reference. Defaults to the parent model's primary key.

### Has one merged

If you only need one model of the merged relation, for example the latest message of a user, you can use the `hasOneMerged` relation.
Add an ordering to the relation to define which model is returned.
If no related model exists, the relation returns `null`.

```php
use Illuminate\Database\Eloquent\Model;
use Korridor\LaravelHasManyMerged\HasOneMerged;
use Korridor\LaravelHasManyMerged\HasOneMergedRelation;

class User extends Model
{
    use HasOneMergedRelation;

    // ...

    /**
     * @return HasOneMerged<Message, $this>
     */
    public function latestMessage(): HasOneMerged
    {
        return $this->hasOneMerged(Message::class, ['sender_user_id', 'receiver_user_id'])
            ->latest();
    }
}
```

The relation supports lazy and eager loading, so you can for example sort users by their latest message without N+1 queries:

```php
$users = User::with('latestMessage')
    ->get()
    ->sortByDesc(fn (User $user) => $user->latestMessage?->created_at);
```

Note that eager loading fetches all matching related models and keeps the first one per parent, similar to Laravel's `hasOne` relation without `ofMany`.

### Limitations

Both relations are read-only, because it is ambiguous which of the foreign keys should be set when creating a related model.
Methods like `save()` or `saveMany()` are not available.
Do not use `create()` or `make()` on the relations either: they are passed on to the query builder and create a model **without any foreign key set**.
Use the underlying hasMany relations (for example `sentMessages()`) to create related models.

## Contributing

I am open for suggestions and contributions. Just create an issue or a pull request.

### Local docker environment

The `docker` folder contains a local docker environment for development.
The docker workspace has composer and xdebug installed.

```bash
docker compose -f docker/docker-compose.yml run workspace bash
```

### Testing

The `composer test` command runs all tests with [phpunit](https://phpunit.de/).
The `composer test-coverage` command runs all tests with phpunit and creates a coverage report into the `coverage` folder.

### Code formatting/Linting

The `composer fix` command formats the code with [php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer).
The `composer lint` command checks the code with [phpcs](https://github.com/squizlabs/PHP_CodeSniffer).
The CI pipeline runs both tools, so make sure that both pass before opening a pull request.

### Static analysis

The `composer analyse` command checks the code with [Larastan](https://github.com/larastan/larastan) (PHPStan) on level 9.

## License

This package is licensed under the MIT License (MIT). Please see [license file](license.md) for more information.
