<?php

namespace Laravel\Horizon\Tests\Feature;

use InvalidArgumentException;
use Laravel\Horizon\Contracts\FileWatcher;
use Laravel\Horizon\Tests\Feature\Fakes\FakeFileWatcher;
use Laravel\Horizon\Tests\IntegrationTest;
use stdClass;

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

    public function test_listen_command_resolves_the_configured_file_watcher()
    {
        FakeFileWatcher::reset();

        config(['horizon.watch' => ['app']]);
        config(['horizon.file_watcher' => FakeFileWatcher::class]);

        $this->artisan('horizon:listen');

        $this->assertTrue(FakeFileWatcher::$built);
        $this->assertNotEmpty(FakeFileWatcher::$lastPaths);
        $this->assertStringEndsWith('/app', FakeFileWatcher::$lastPaths[0]);
    }

    public function test_listen_command_forwards_poll_option_to_watcher_constructor()
    {
        FakeFileWatcher::reset();

        config(['horizon.watch' => ['app']]);
        config(['horizon.file_watcher' => FakeFileWatcher::class]);

        $this->artisan('horizon:listen', ['--poll' => true]);

        $this->assertTrue(FakeFileWatcher::$lastPoll);
    }

    public function test_listen_command_throws_when_file_watcher_does_not_implement_contract()
    {
        config(['horizon.watch' => ['app']]);
        config(['horizon.file_watcher' => stdClass::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement '.FileWatcher::class);

        $this->artisan('horizon:listen');
    }
}
