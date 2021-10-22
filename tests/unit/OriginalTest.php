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
            $this->queue->connect();
        } catch (Exception $e) {
            $this->markTestSkipped($e->getMessage());

            return;
        }

        //we don't need any existing job in the table for this class
        $this->getModule('Db')->_getDbh()->exec('DELETE FROM jobs');
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
        $this->assertNotFalse($this->queue->choose('beanstalk-test'));

        $task = 'doSomething';

        $this->assertNotFalse($this->queue->put($task));

        $this->assertNotFalse($this->queue->watch('beanstalk-test'));

        $job = $this->queue->reserve(0);

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($task, $job->getBody());

//        $this->assertTrue($job->touch());

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

        $this->assertTrue($job->kick());
        $job = $this->queue->peekReady();

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($task, $job->getBody());

        $this->assertTrue($job->delete());
    }

    public function testStats()
    {
        $this->assertNotFalse($this->queue->choose('beanstalk-test'));

        $queueStats = $this->queue->stats();
        $this->assertIsArray( $queueStats);

        $tubeStats = $this->queue->statsTube('beanstalk-test');
        $this->assertIsArray( $tubeStats);
        $this->assertEquals('beanstalk-test', $tubeStats['name']);

        $this->assertFalse($this->queue->statsTube('beanstalk-test-does-not-exist'));

        $this->assertNotFalse($this->queue->choose('beanstalk-test'));

        $this->queue->put('doSomething');

        $this->queue->watch('beanstalk-test');

        $job      = $this->queue->peekReady();
        $jobStats = (array)$job->stats(); //there's no way for an ArrayObject be defined as scalar array, so...

        $this->assertIsArray( $jobStats);
        $this->assertEquals('beanstalk-test', $jobStats['tube']);
    }
}
