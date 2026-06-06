<?php

namespace Laravel\Horizon\Tests\Feature\Fakes;

use Laravel\Horizon\Contracts\FileWatcher;

class FakeFileWatcher implements FileWatcher
{
    /**
     * Indicates if start() has been called.
     *
     * @var bool
     */
    public static $started = false;

    /**
     * The paths captured by the most recent start() call.
     *
     * @var array
     */
    public static $lastPaths = [];

    /**
     * Indicates if stop() has been called.
     *
     * @var bool
     */
    public static $stopped = false;

    /**
     * Begin watching the given paths for changes.
     *
     * @param  array  $paths
     * @return void
     */
    public function start(array $paths)
    {
        static::$started = true;
        static::$lastPaths = $paths;
    }

    /**
     * Determine if any watched files have changed since the last check.
     *
     * @return bool
     */
    public function changed()
    {
        return false;
    }

    /**
     * Stop watching for changes and release any resources.
     *
     * @return void
     */
    public function stop()
    {
        static::$stopped = true;
    }

    /**
     * Reset the recorded fake state between tests.
     *
     * @return void
     */
    public static function reset()
    {
        static::$started = false;
        static::$lastPaths = [];
        static::$stopped = false;
    }
}
