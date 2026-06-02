<?php

namespace Laravel\Horizon\FileWatchers;

use InvalidArgumentException;
use Laravel\Horizon\Contracts\FileWatcher;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ChokidarFileWatcher implements FileWatcher
{
    /**
     * Indicates if the watcher should use polling.
     *
     * @var bool
     */
    public $poll;

    /**
     * Create a new file watcher instance.
     *
     * @param  bool  $poll
     * @return void
     */
    public function __construct($poll = false)
    {
        $this->poll = $poll;
    }

    /**
     * Build the process that watches the given paths for changes.
     *
     * @param  array  $paths
     * @return \Symfony\Component\Process\Process
     */
    public function build(array $paths)
    {
        $nodeExecutable = (new ExecutableFinder())->find('node');

        if (! $nodeExecutable) {
            throw new InvalidArgumentException(
                'Node could not be found. Please ensure Node is installed and available in your system PATH.',
            );
        }

        return new Process([
            $nodeExecutable,
            'file-watcher.cjs',
            json_encode($paths),
            $this->poll ? '1' : '',
        ], __DIR__ . '/../../bin', ['NODE_PATH' => base_path('node_modules')], null, null);
    }
}
