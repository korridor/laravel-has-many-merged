# Laravel has many merged

[![Latest Version on Packagist](https://img.shields.io/packagist/v/korridor/laravel-has-many-merged?style=flat-square)](https://packagist.org/packages/korridor/laravel-has-many-merged)
[![License](https://img.shields.io/packagist/l/korridor/laravel-has-many-merged?style=flat-square)](license.md)
[![Codecov](https://img.shields.io/codecov/c/github/korridor/laravel-has-many-merged?style=flat-square)](https://codecov.io/gh/korridor/laravel-has-many-merged)
[![TravisCI](https://img.shields.io/travis/korridor/laravel-has-many-merged?style=flat-square)](https://travis-ci.org/korridor/laravel-has-many-merged)
[![StyleCI](https://styleci.io/repos/339041939/shield)](https://styleci.io/repos/339041939)

Custom relationship for Eloquent that merges/combines multiple one-to-may (hasMany) relationships.
This relation fully supports lazy and eager loading.

## Installation

You can install the package via composer with following command:

```bash
composer require korridor/laravel-has-many-merged
```

### Requirements

This package is tested for the following Laravel versions:

- 8.* (PHP 7.3, 7.4, 8.0)
- 7.* (PHP 7.2, 7.3, 7.4)
- 6.* (PHP 7.2, 7.3)

## Usage examples

In the following example there are two models User and Message.
Each message has a sender and a receiver.
The User model has two hasMany relations - one for the sent messages and the other for the received ones.

With this plugin you can add a relation that contains sent and received messages of a user.

```php
use Korridor\LaravelHasManyMerged\HasManyMerged;
use Korridor\LaravelHasManyMerged\HasManyMergedRelation;

class User extends Model
{
    use HasManyMergedRelation;
    
    // ...

    /**
     * @return HasManyMerged|Message
     */
    public function messages()
    {
        return $this->hasManyMerged(Message::class, ['sender_user_id', 'receiver_user_id']);
    }

    /**
     * @return HasMany|Message
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_user_id');
    }

    /**
     * @return HasMany|Message
     */
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_user_id');
    }
}
```

## Contributing

I am open for suggestions and contributions. Just create an issue or a pull request.

### Local docker environment

The `docker` folder contains a local docker environment for development.
The docker workspace has composer and xdebug installed.

```bash
docker-compose run workspace bash
```

### Testing

The `composer test` command runs all tests with [phpunit](https://phpunit.de/).
The `composer test-coverage` command runs all tests with phpunit and creates a coverage report into the `coverage` folder.

### Codeformatting/Linting

The `composer fix` command formats the code with [php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer).
The `composer lint` command checks the code with [phpcs](https://github.com/squizlabs/PHP_CodeSniffer).

## License

This package is licensed under the MIT License (MIT). Please see [license file](license.md) for more information.
