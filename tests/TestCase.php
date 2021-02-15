<?php

namespace Korridor\LaravelHasManyMerged\Tests;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new Manager();
        $this->db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->db->setAsGlobal();
        $this->db->bootEloquent();

        $this->db::schema()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('other_unique_id')->unique();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->db::schema()->create('messages', function (Blueprint $table) {
            $table->increments('id');
            $table->text('content');
            $table->unsignedInteger('sender_user_id');
            $table->unsignedInteger('receiver_user_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
