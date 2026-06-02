<?php

namespace Laravel\Horizon\Contracts;

interface FileWatcher
{
    /**
     * Build the process that watches the given paths for changes.
     * Any output written to the process's standard output will be treated
     * as a change notification and will trigger a Horizon restart.
     *
     * @param  array  $paths
     * @return \Symfony\Component\Process\Process
     */
    public function build(array $paths);
}
