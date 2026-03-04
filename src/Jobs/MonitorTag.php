<?php

namespace Laravel\Horizon\Jobs;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;

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

        $this->backfill($tags, $jobs);
    }

    /**
     * Backfill existing jobs that match the monitored tag.
     *
     * @param  \Laravel\Horizon\Contracts\TagRepository  $tags
     * @param  \Laravel\Horizon\Contracts\JobRepository  $jobs
     * @return void
     */
    protected function backfill(TagRepository $tags, JobRepository $jobs)
    {
        $backfillMinutes = config('horizon.trim.monitor_backfill', 43200);

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
     *
     * @param  \Laravel\Horizon\Contracts\TagRepository  $tags
     * @param  \Laravel\Horizon\Contracts\JobRepository  $jobs
     * @param  string  $type
     * @param  int  $cutoff
     * @param  array  &$seen
     * @return void
     */
    protected function backfillFromType(TagRepository $tags, JobRepository $jobs, string $type, int $cutoff, array &$seen)
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
                    continue;
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

                    if (in_array($this->tag, $jobTags)) {
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
