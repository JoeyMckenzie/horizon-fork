<?php

namespace Laravel\Horizon\FileWatchers;

use FilesystemIterator;
use InvalidArgumentException;
use Laravel\Horizon\Contracts\FileWatcher;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A no-dependency file watcher that polls the filesystem on each check.
 *
 * Intended for environments where chokidar (Node) is not available. Cost is
 * O(files) per call to changed(), so it is best suited for the modest set
 * of paths in Horizon's default watch list.
 *
 * Supported path patterns:
 *  - Bare files or directories (e.g. ".env", "app").
 *  - "<dir>/*.<ext>" or "<dir>/**\/*.<ext>" — recursive scan filtered by extension.
 *
 * Patterns containing wildcards in any other position will throw rather than
 * silently watch the wrong files.
 */
class PollingFileWatcher implements FileWatcher
{
    /**
     * The paths being watched.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * The last filesystem snapshot keyed by absolute path.
     *
     * @var array
     */
    protected $snapshot = [];

    /**
     * Begin watching the given paths for changes.
     *
     * @param  array  $paths
     * @return void
     */
    public function start(array $paths)
    {
        $this->paths = $paths;
        $this->snapshot = $this->scan();
    }

    /**
     * Determine if any watched files have changed since the last check.
     *
     * @return bool
     */
    public function changed()
    {
        $current = $this->scan();

        if ($current === $this->snapshot) {
            return false;
        }

        $this->snapshot = $current;

        return true;
    }

    /**
     * Stop watching for changes and release any resources.
     *
     * @return void
     */
    public function stop()
    {
        $this->paths = [];
        $this->snapshot = [];
    }

    /**
     * Build a snapshot of every watched file mapped to its last-modified time.
     *
     * @return array
     */
    protected function scan()
    {
        $files = [];

        foreach ($this->paths as $path) {
            foreach ($this->expand($path) as $file) {
                $mtime = @filemtime($file);

                if ($mtime !== false) {
                    $files[$file] = $mtime;
                }
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Expand a watched path entry into the list of files to track.
     *
     * @param  string  $path
     * @return array
     */
    protected function expand($path)
    {
        if (strpos($path, '*') === false) {
            if (is_file($path)) {
                return [$path];
            }

            return is_dir($path) ? $this->scanDirectory($path) : [];
        }

        if (! preg_match('#^([^*]+?)/(?:\*\*/)?\*\.([\w.]+)$#', $path, $matches)) {
            throw new InvalidArgumentException(sprintf(
                'The polling file watcher does not support the glob pattern [%s]. '.
                'Supported patterns are bare paths, "<dir>/*.<ext>", and "<dir>/**/*.<ext>".',
                $path,
            ));
        }

        [$_, $base, $extension] = $matches;

        if (! is_dir($base)) {
            return [];
        }

        $suffix = '.'.$extension;

        return array_values(array_filter(
            $this->scanDirectory($base),
            function ($file) use ($suffix) {
                return substr($file, -strlen($suffix)) === $suffix;
            },
        ));
    }

    /**
     * Recursively list every regular file beneath the given directory.
     *
     * @param  string  $directory
     * @return array
     */
    protected function scanDirectory($directory)
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
