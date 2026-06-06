<?php

namespace Laravel\Horizon\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Laravel\Horizon\Contracts\FileWatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'horizon:listen')]
class ListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horizon:listen
        {--environment= : The environment name}
        {--poll : Deprecated. Set "horizon.chokidar.poll" in your configuration instead}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Horizon and automatically restart workers on file changes';

    /**
     * The Horizon process instance.
     *
     * @var \Symfony\Component\Process\Process|null
     */
    protected $horizonProcess;

    /**
     * The file watcher instance.
     *
     * @var \Laravel\Horizon\Contracts\FileWatcher|null
     */
    protected $watcher;

    /**
     * Indicates if a termination signal has been received.
     *
     * @var int|null
     */
    protected $trappedSignal = null;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->components->info('Starting Horizon and watching for file changes...');

        if ($this->option('poll')) {
            $this->components->warn('The --poll option is deprecated. Set "horizon.chokidar.poll" in your configuration instead.');

            config(['horizon.chokidar.poll' => true]);
        }

        $this->watcher = $this->startWatcher();

        if (! $this->startHorizon()) {
            return Command::FAILURE;
        }

        $this->listenForChanges();

        return Command::SUCCESS;
    }

    /**
     * Resolve the file watcher and start watching the configured paths.
     *
     * @return \Laravel\Horizon\Contracts\FileWatcher
     */
    protected function startWatcher()
    {
        if (empty($paths = config('horizon.watch'))) {
            throw new InvalidArgumentException(
                'List of directories / files to watch not found. Please update your "config/horizon.php" configuration file.',
            );
        }

        $watcher = app(FileWatcher::class);

        $watcher->start(
            collect($paths)->map(fn ($path) => base_path($path))->values()->all(),
        );

        return $watcher;
    }

    /**
     * Start the Horizon process.
     *
     * @return bool
     */
    protected function startHorizon()
    {
        $command = ['php', 'artisan', 'horizon'];

        if ($environment = $this->option('environment')) {
            $command[] = '--environment='.$environment;
        }

        $this->horizonProcess = (new Process($command))
            ->setTimeout(null);

        $this->trap([SIGINT, SIGTERM, SIGQUIT], function ($signal) {
            $this->trappedSignal = $signal;

            $this->horizonProcess->stop(signal: $signal);
            $this->horizonProcess->wait();

            if ($this->watcher) {
                $this->watcher->stop();
            }
        });

        $this->horizonProcess->start();

        usleep(100_000);

        return ! $this->horizonProcess->isTerminated();
    }

    /**
     * Listen for file changes and restart Horizon when detected.
     *
     * @return void
     */
    protected function listenForChanges()
    {
        while (! $this->trappedSignal) {
            if ($this->watcher->changed()) {
                $this->restartHorizon();
            }

            $this->output->write($this->horizonProcess->getIncrementalOutput());

            if (! $this->horizonProcess->isRunning()) {
                break;
            }

            usleep(500_000);
        }
    }

    /**
     * Restart the Horizon process.
     *
     * @return void
     */
    protected function restartHorizon()
    {
        $this->components->info('File changed. Restarting Horizon...');

        $this->horizonProcess->stop();
        $this->horizonProcess->wait();

        $this->startHorizon();
    }
}
