<?php

namespace Laravel\Horizon\Tests\Feature\Fakes;

use Laravel\Horizon\Contracts\FileWatcher;
use Symfony\Component\Process\Process;

class FakeFileWatcher implements FileWatcher
{
    public static $built = false;

    public static $lastPaths = [];

    public static $lastPoll = null;

    public function __construct($poll = false)
    {
        static::$lastPoll = $poll;
    }

    public function build(array $paths)
    {
        static::$built = true;
        static::$lastPaths = $paths;

        return new Process(['php', '-r', 'exit(0);']);
    }

    public static function reset()
    {
        static::$built = false;
        static::$lastPaths = [];
        static::$lastPoll = null;
    }
}
