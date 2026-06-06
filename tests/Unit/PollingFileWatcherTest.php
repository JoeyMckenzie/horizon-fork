<?php

namespace Laravel\Horizon\Tests\Unit;

use InvalidArgumentException;
use Laravel\Horizon\FileWatchers\PollingFileWatcher;
use Laravel\Horizon\Tests\UnitTest;

class PollingFileWatcherTest extends UnitTest
{
    /**
     * The temporary directory used for each test.
     *
     * @var string
     */
    protected $tempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDirectory = sys_get_temp_dir().'/horizon-watcher-'.uniqid();
        mkdir($this->tempDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDirectory);

        parent::tearDown();
    }

    public function test_changed_returns_false_when_no_files_have_changed()
    {
        file_put_contents($this->tempDirectory.'/a.php', 'a');

        $watcher = new PollingFileWatcher;
        $watcher->start([$this->tempDirectory]);

        $this->assertFalse($watcher->changed());
    }

    public function test_changed_returns_true_when_a_watched_file_is_modified()
    {
        $file = $this->tempDirectory.'/a.php';
        file_put_contents($file, 'a');
        touch($file, time() - 10);

        $watcher = new PollingFileWatcher;
        $watcher->start([$this->tempDirectory]);

        touch($file, time());

        $this->assertTrue($watcher->changed());
    }

    public function test_changed_returns_true_when_a_new_file_is_added()
    {
        file_put_contents($this->tempDirectory.'/a.php', 'a');

        $watcher = new PollingFileWatcher;
        $watcher->start([$this->tempDirectory]);

        file_put_contents($this->tempDirectory.'/b.php', 'b');

        $this->assertTrue($watcher->changed());
    }

    public function test_changed_returns_true_when_a_watched_file_is_removed()
    {
        $file = $this->tempDirectory.'/a.php';
        file_put_contents($file, 'a');

        $watcher = new PollingFileWatcher;
        $watcher->start([$this->tempDirectory]);

        unlink($file);

        $this->assertTrue($watcher->changed());
    }

    public function test_changed_resets_baseline_so_consecutive_calls_are_idempotent()
    {
        $file = $this->tempDirectory.'/a.php';
        file_put_contents($file, 'a');
        touch($file, time() - 10);

        $watcher = new PollingFileWatcher;
        $watcher->start([$this->tempDirectory]);

        touch($file, time());

        $this->assertTrue($watcher->changed());
        $this->assertFalse($watcher->changed());
    }

    public function test_glob_patterns_filter_files_by_extension()
    {
        file_put_contents($this->tempDirectory.'/keep.php', 'a');
        file_put_contents($this->tempDirectory.'/ignore.txt', 'a');
        touch($this->tempDirectory.'/keep.php', time() - 10);
        touch($this->tempDirectory.'/ignore.txt', time() - 10);

        $watcher = new PollingFileWatcher;
        $watcher->start([$this->tempDirectory.'/**/*.php']);

        touch($this->tempDirectory.'/ignore.txt', time());
        $this->assertFalse($watcher->changed());

        touch($this->tempDirectory.'/keep.php', time());
        $this->assertTrue($watcher->changed());
    }

    public function test_unsupported_glob_patterns_throw_rather_than_silently_match_nothing()
    {
        $watcher = new PollingFileWatcher;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not support the glob pattern');

        $watcher->start(['*.{php,blade.php}']);
    }

    public function test_mid_path_wildcards_throw()
    {
        $watcher = new PollingFileWatcher;

        $this->expectException(InvalidArgumentException::class);

        $watcher->start(['app/*/foo/*.php']);
    }

    /**
     * Recursively delete a directory.
     *
     * @param  string  $path
     * @return void
     */
    protected function deleteDirectory($path)
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path.'/'.$entry;

            if (is_dir($full)) {
                $this->deleteDirectory($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
