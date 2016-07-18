<?php
use Phalcon\Queue\Db\Job;

/**
 * Test based on the original BeanstalkTest from Phalcon Framework
 * @see https://raw.githubusercontent.com/phalcon/cphalcon/master/unit-tests/BeanstalkTest.php
 */
class OriginalTest extends \Codeception\TestCase\Test
{
    /** @var Phalcon\Queue\Db */
    protected $queue;

    public function _before()
    {
        try {
            $this->queue = new Phalcon\Queue\Db();
//            @$this->queue->connect();
        } catch (Exception $e) {
            $this->markTestSkipped($e->getMessage());

            return;
        }
    }

    public function testBasic()
    {
        $expected = ['processVideo' => 4871];

        $this->queue->put($expected);
        while (($job = $this->queue->peekReady()) !== false) {
            $this->assertInstanceOf(Job::class, $job);
            $actual = $job->getBody();
            $job->delete();
            $this->assertEquals($expected, $actual);
        }
    }

    public function testReleaseKickBury()
    {
        $this->assertTrue($this->queue->choose('beanstalk-test') !== false);

        $task = 'doSomething';

        $this->assertTrue($this->queue->put($task) !== false);

        $this->assertTrue($this->queue->watch('beanstalk-test') !== false);

        $job = $this->queue->reserve(0);

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($task, $job->getBody());

        $this->assertTrue($job->touch());

        // Release the job; it moves to the ready queue
        $this->assertTrue($job->release());
        $job = $this->queue->reserve(0);

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($task, $job->getBody());

        // Bury the job
        $this->assertTrue($job->bury());
        $job = $this->queue->peekBuried();

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($task, $job->getBody());

        // Kick the job, it should move to the ready queue again
        // kick-job is supported since 1.8
        if (false !== $job->kick()) {
            $job = $this->queue->peekReady();

            $this->assertInstanceOf(Job::class, $job);
            $this->assertEquals($task, $job->getBody());
        }

        $this->assertTrue($job->delete());
    }

    public function testStats()
    {
        $this->assertTrue($this->queue->choose('beanstalk-test') !== false);

        $queueStats = $this->queue->stats();
        $this->assertTrue(is_array($queueStats));

        $tubeStats = $this->queue->statsTube('beanstalk-test');
        $this->assertTrue(is_array($tubeStats));
        $this->assertTrue($tubeStats['name'] === 'beanstalk-test');

        $this->assertTrue($this->queue->statsTube('beanstalk-test-does-not-exist') === false);

        $this->assertTrue($this->queue->choose('beanstalk-test') !== false);

        $this->queue->put('doSomething');

        $this->queue->watch('beanstalk-test');

        $job      = $this->queue->peekReady();
        $jobStats = $job->stats();

        $this->assertTrue(is_array($jobStats));
        $this->assertTrue($jobStats['tube'] === 'beanstalk-test');
    }
}
