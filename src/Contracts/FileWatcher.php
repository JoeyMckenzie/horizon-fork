<?php

namespace Laravel\Horizon\Contracts;

/**
 * Contract for watching files for changes during `horizon:listen`.
 *
 * Implementations have a lifecycle of: a single call to start() with the
 * paths to watch, followed by repeated calls to changed() on each tick of
 * the listen loop, followed by a single call to stop() when the command
 * is terminating.
 */
interface FileWatcher
{
    /**
     * Begin watching the given paths for changes.
     *
     * @param  array  $paths
     * @return void
     */
    public function start(array $paths);

    /**
     * Determine if any watched files have changed since the last check.
     *
     * @return bool
     */
    public function changed();

    /**
     * Stop watching for changes and release any resources.
     *
     * @return void
     */
    public function stop();
}
