<?php

namespace RPillz\LaravelVisitor\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use RPillz\LaravelVisitor\LaravelVisitorServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'RPillz\\LaravelVisitor\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelVisitorServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.visitor', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('visitor.connection', 'visitor');

        $migration = include __DIR__.'/../database/migrations/create_visits_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_visitor_ignores_table.php.stub';
        $migration->up();

    }
}
