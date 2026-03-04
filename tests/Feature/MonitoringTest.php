<?php

namespace Laravel\Horizon\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Jobs\MonitorTag;
use Laravel\Horizon\Jobs\StopMonitoringTag;
use Laravel\Horizon\Tests\IntegrationTest;

class MonitoringTest extends IntegrationTest
{
    public function test_can_retrieve_all_monitored_tags()
    {
        $repository = resolve(TagRepository::class);

        dispatch(new MonitorTag('first'));
        $this->assertEquals(['first'], $repository->monitoring());

        dispatch(new MonitorTag('second'));
        $monitored = $repository->monitoring();
        $this->assertContains('first', $monitored);
        $this->assertContains('second', $monitored);
        $this->assertCount(2, $monitored);
    }

    public function test_can_determine_if_a_set_of_tags_are_being_monitored()
    {
        $repository = resolve(TagRepository::class);
        dispatch(new MonitorTag('first'));
        $this->assertEquals(['first'], $repository->monitored(['first', 'second']));
    }

    public function test_can_stop_monitoring_tags()
    {
        $repository = resolve(TagRepository::class);
        dispatch(new MonitorTag('first'));
        dispatch(new StopMonitoringTag('first'));
        $this->assertEquals([], $repository->monitored(['first', 'second']));
    }

    public function test_tags_that_are_removed_from_monitoring_are_removed_from_storage()
    {
        dispatch(new MonitorTag('first'));
        dispatch(new StopMonitoringTag('first'));
        $this->assertNull(Redis::connection('horizon')->get('first'));
    }

    public function test_completed_jobs_are_stored_in_database_when_one_of_their_tags_is_being_monitored()
    {
        dispatch(new MonitorTag('first'));
        $id = Queue::push(new Jobs\BasicJob);
        $this->work();
        $this->assertSame(1, $this->monitoredJobs('first'));
        $this->assertGreaterThan(0, Redis::connection('horizon')->ttl($id));
    }

    public function test_completed_jobs_are_removed_from_database_when_their_tag_is_no_longer_monitored()
    {
        dispatch(new MonitorTag('first'));
        Queue::push(new Jobs\BasicJob);
        $this->work();
        dispatch(new StopMonitoringTag('first'));
        $this->assertSame(0, $this->monitoredJobs('first'));
    }

    public function test_all_completed_jobs_are_removed_from_database_when_their_tag_is_no_longer_monitored()
    {
        dispatch(new MonitorTag('first'));

        for ($i = 0; $i < 80; $i++) {
            Queue::push(new Jobs\BasicJob);
        }

        $this->work();

        dispatch(new StopMonitoringTag('first'));
        $this->assertSame(0, $this->monitoredJobs('first'));
    }

    public function test_existing_completed_jobs_are_backfilled_when_monitor_is_created()
    {
        Queue::push(new Jobs\BasicJob);
        $this->work();

        // Monitor created AFTER job was processed
        dispatch(new MonitorTag('first'));

        $this->assertGreaterThan(0, $this->monitoredJobs('first'));
    }

    public function test_backfill_does_not_duplicate_jobs()
    {
        Queue::push(new Jobs\BasicJob);
        $this->work();

        dispatch(new MonitorTag('first'));
        $countAfterFirst = $this->monitoredJobs('first');

        // Dispatch again — should not double-count
        dispatch(new MonitorTag('first'));
        $this->assertSame($countAfterFirst, $this->monitoredJobs('first'));
    }

    public function test_backfill_only_matches_jobs_with_the_monitored_tag()
    {
        Queue::push(new Jobs\BasicJob);
        $this->work();

        // BasicJob has tags ['first', 'second'], so 'nonexistent' should find nothing
        dispatch(new MonitorTag('nonexistent'));

        $this->assertSame(0, $this->monitoredJobs('nonexistent'));
    }

    public function test_backfill_handles_large_job_volumes()
    {
        for ($i = 0; $i < 65; $i++) {
            Queue::push(new Jobs\BasicJob);
        }

        $this->work(65);

        dispatch(new MonitorTag('first'));

        $this->assertSame(65, $this->monitoredJobs('first'));
    }

    public function test_backfill_is_skipped_when_config_is_zero()
    {
        Queue::push(new Jobs\BasicJob);
        $this->work();

        config(['horizon.trim.monitor_backfill' => 0]);

        dispatch(new MonitorTag('first'));

        $this->assertSame(0, $this->monitoredJobs('first'));
    }
}
