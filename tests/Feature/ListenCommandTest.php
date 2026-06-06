<?php

namespace Laravel\Horizon\Tests\Feature;

use InvalidArgumentException;
use Laravel\Horizon\Contracts\FileWatcher;
use Laravel\Horizon\FileWatchers\ChokidarFileWatcher;
use Laravel\Horizon\Tests\Feature\Fakes\FakeFileWatcher;
use Laravel\Horizon\Tests\IntegrationTest;

class ListenCommandTest extends IntegrationTest
{
    public function test_listen_command_requires_watch_configuration()
    {
        config(['horizon.watch' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List of directories / files to watch not found.');

        $this->artisan('horizon:listen');
    }

    public function test_listen_command_requires_watch_configuration_to_be_set()
    {
        config(['horizon.watch' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List of directories / files to watch not found.');

        $this->artisan('horizon:listen');
    }

    public function test_listen_command_requires_watch_configuration_key_to_exist()
    {
        $config = config('horizon');
        unset($config['watch']);
        config(['horizon' => $config]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List of directories / files to watch not found.');

        $this->artisan('horizon:listen');
    }

    public function test_file_watcher_contract_defaults_to_chokidar()
    {
        $this->assertInstanceOf(
            ChokidarFileWatcher::class,
            $this->app->make(FileWatcher::class),
        );
    }

    public function test_file_watcher_contract_can_be_overridden_by_user_binding()
    {
        $this->app->bind(FileWatcher::class, FakeFileWatcher::class);

        $this->assertInstanceOf(
            FakeFileWatcher::class,
            $this->app->make(FileWatcher::class),
        );
    }

    public function test_listen_command_starts_the_bound_watcher_with_absolute_paths()
    {
        FakeFileWatcher::reset();

        $this->app->bind(FileWatcher::class, FakeFileWatcher::class);

        config(['horizon.watch' => ['app']]);

        $this->artisan('horizon:listen');

        $this->assertTrue(FakeFileWatcher::$started);
        $this->assertCount(1, FakeFileWatcher::$lastPaths);
        $this->assertStringEndsWith('/app', FakeFileWatcher::$lastPaths[0]);
    }
}
