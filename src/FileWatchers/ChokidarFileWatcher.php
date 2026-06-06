<?php

namespace Laravel\Horizon\FileWatchers;

use InvalidArgumentException;
use Laravel\Horizon\Contracts\FileWatcher;
use RuntimeException;
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
     * The chokidar watcher process.
     *
     * @var \Symfony\Component\Process\Process|null
     */
    protected $process;

    /**
     * Create a new file watcher instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->poll = (bool) config('horizon.chokidar.poll', false);
    }

    /**
     * Begin watching the given paths for changes.
     *
     * @param  array  $paths
     * @return void
     */
    public function start(array $paths)
    {
        $nodeExecutable = (new ExecutableFinder)->find('node');

        if (! $nodeExecutable) {
            throw new InvalidArgumentException(
                'Node could not be found. Please ensure Node is installed and available in your system PATH.',
            );
        }

        $this->process = new Process([
            $nodeExecutable,
            'file-watcher.cjs',
            json_encode($paths),
            $this->poll ? '1' : '',
        ], __DIR__.'/../../bin', ['NODE_PATH' => base_path('node_modules')], null, null);

        $this->process->start();

        sleep(1);

        if ($this->process->isTerminated()) {
            throw new RuntimeException(trim(
                'Failed to start the chokidar file watcher. Please ensure Node.js and the chokidar npm package are installed. '
                .$this->process->getErrorOutput()
            ));
        }
    }

    /**
     * Determine if any watched files have changed since the last check.
     *
     * @return bool
     */
    public function changed()
    {
        return $this->process && $this->process->getIncrementalOutput() !== '';
    }

    /**
     * Stop watching for changes and release any resources.
     *
     * @return void
     */
    public function stop()
    {
        if ($this->process) {
            $this->process->stop();
            $this->process = null;
        }
    }
}
