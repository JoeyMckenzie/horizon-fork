<?php

namespace Laravel\Horizon\Jobs;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Repositories\RedisJobRepository;
use Laravel\Horizon\Repositories\RedisTagRepository;

class MonitorTag
{
    /**
     * The job type ZSETs to scan during backfill.
     *
     * @var list<string>
     */
    protected $jobTypes = [
        'recent_jobs',
        'completed_jobs',
        'failed_jobs',
        'pending_jobs',
        'silenced_jobs',
    ];

    /**
     * Create a new job instance.
     *
     * @param  string  $tag  The tag to monitor.
     * @return void
     */
    public function __construct(
        public $tag,
    ) {}

    /**
     * Execute the job.
     *
     * @param  \Laravel\Horizon\Contracts\TagRepository  $tags
     * @param  \Laravel\Horizon\Contracts\JobRepository  $jobs
     * @return void
     */
    public function handle(TagRepository $tags, JobRepository $jobs)
    {
        $tags->monitor($this->tag);

        if ($tags instanceof RedisTagRepository && $jobs instanceof RedisJobRepository) {
            $this->backfill($tags, $jobs);
        }
    }

    /**
     * Backfill existing jobs that match the monitored tag.
     *
     * @param  \Laravel\Horizon\Repositories\RedisTagRepository  $tags
     * @param  \Laravel\Horizon\Repositories\RedisJobRepository  $jobs
     * @return void
     */
    protected function backfill(RedisTagRepository $tags, RedisJobRepository $jobs)
    {
        $backfillMinutes = config('horizon.trim.monitor_backfill', 10080);

        if ($backfillMinutes === 0) {
            return;
        }

        $cutoff = now()->subMinutes($backfillMinutes)->getTimestamp();
        $seen = [];

        foreach ($this->jobTypes as $type) {
            $this->backfillFromType($tags, $jobs, $type, $cutoff, $seen);
        }
    }

    /**
     * Backfill jobs from a specific job type ZSET.
     * Once we encounter a job older than the cutoff, all subsequent entries
     * in the ZSET are guaranteed to be older, so we can stop scanning.
     *
     * @param  \Laravel\Horizon\Repositories\RedisTagRepository  $tags
     * @param  \Laravel\Horizon\Repositories\RedisJobRepository  $jobs
     * @param  string  $type
     * @param  int  $cutoff
     * @param  array  &$seen
     * @return void
     */
    protected function backfillFromType(RedisTagRepository $tags, RedisJobRepository $jobs, string $type, int $cutoff, array &$seen)
    {
        $offset = 0;
        $pageSize = 50;

        while (true) {
            $entries = $jobs->getJobIdsByType($type, $offset, $pageSize);

            if (empty($entries)) {
                break;
            }

            $ids = [];
            $scoreMap = [];

            foreach ($entries as $entry) {
                $timestamp = abs($entry['score']);

                if ($timestamp < $cutoff) {
                    return;
                }

                if (isset($seen[$entry['id']])) {
                    continue;
                }

                $seen[$entry['id']] = true;
                $ids[] = $entry['id'];
                $scoreMap[$entry['id']] = $timestamp;
            }

            if (! empty($ids)) {
                $jobData = $jobs->getJobs($ids);

                $matching = [];

                foreach ($jobData as $job) {
                    $payload = json_decode($job->payload, true);
                    $jobTags = $payload['tags'] ?? [];

                    if (in_array($this->tag, $jobTags, true)) {
                        $matching[] = [
                            'id' => $job->id,
                            'score' => $scoreMap[$job->id],
                        ];
                    }
                }

                $tags->addBatch($this->tag, $matching);
            }

            if (count($entries) < $pageSize) {
                break;
            }

            $offset += $pageSize;
        }
    }
}
