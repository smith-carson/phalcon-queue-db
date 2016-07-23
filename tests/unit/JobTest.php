<?php
use Phalcon\Queue\Db;
use Phalcon\Queue\Db\InvalidJobOperationException as InvalidJobOperation;
use Phalcon\Queue\Db\Job;
use Phalcon\Queue\Db\Model as JobModel;

class JobTest extends \Codeception\TestCase\Test
{

    /** @var \UnitTester */
    protected $tester;

    /**
     * @param null $criteria Query to run through to get a job instance. By default, gets a random job.
     * @return bool|Job
     */
    public function getAJob($criteria = null)
    {
        $model = JobModel::findFirst($criteria?: ['order' => 'RANDOM()']);
        return $model? new Job(new Db(), $model) : false;
    }

    public function testInstance()
    {
        $job = $this->getAJob();
        $this->assertInstanceOf(Job::class, $job);
    }

    public function testGetModel()
    {
        $job = $this->getAJob();
        $this->assertInstanceOf(JobModel::class, $job->getModel());
    }

    public function testGetId()
    {
        $id = 2;
        $job = $this->getAJob($id);
        $this->assertEquals($id, $job->getId());
    }

    public function testGetState()
    {
        $now = time();
        $this->assertEquals(Job::ST_READY, $this->getAJob('tube="int"')->getState());
        $this->assertEquals(Job::ST_READY, $this->getAJob('delay <= '.$now)->getState());
        $this->assertEquals(Job::ST_DELAYED, $this->getAJob('delay > '.$now)->getState());
        $this->assertEquals(Job::ST_BURIED, $this->getAJob('buried = 1')->getState());
        $this->assertEquals(Job::ST_RESERVED, $this->getAJob('reserved = 1')->getState());
        $this->assertEquals(Job::ST_URGENT, $this->getAJob('priority < '.Job::PRIORITY_MEDIUM)->getState());
    }

    public function testGetBody()
    {
        $job = $this->getAJob('tube="int"');
        $this->assertSame(10, $job->getBody());
    }

    /**
     * @depends testGetId
     * @depends testGetState
     */
    public function testDelete()
    {
        $reserved = $this->getAJob('buried = 1 OR reserved = 1');
        $reserved->delete();
        $this->assertFalse($this->getAJob($reserved->getId()));

//        $ready = $this->getAJob('reserved = 0');
//        $this->tester->expectException(InvalidJobOperation::class, function() use ($ready) { $ready->delete(); });
    }

    /** @depends testGetState */
    public function testStats()
    {
        $stats = $this->getAJob()->stats();
        foreach (['age', 'id', 'state', 'tube', 'delay', 'delayed_until', 'priority', 'priority_text'] as $key) {
            $this->assertArrayHasKey($key, $stats, "Key $key not found on job stats");
        }
        $this->assertGreaterThan(0,         $stats['age']);
        $this->assertInternalType('int',    $stats['id']);
        $this->assertGreaterThan(0,         $stats['id']);
        $this->assertInternalType('string', $stats['state']);
        $this->assertNotEmpty(              $stats['state']);
        $this->assertInternalType('string', $stats['tube']);
        $this->assertNotEmpty(              $stats['tube']);
        $this->assertTrue(in_array(         $stats['tube'], DbTest::$tubes), 'invalid tube');
        $this->assertInternalType('int',    $stats['delay']);
        $this->assertGreaterThanOrEqual(0,  $stats['delay']);
        $this->assertInternalType('int',    $stats['delayed_until']); //no need to check for valid timestamp: any int is
        $this->assertInternalType('int',    $stats['priority']);
        $this->assertGreaterThanOrEqual(0,  $stats['priority']);
        $this->assertInternalType('string', $stats['priority_text']);
        $this->assertNotEmpty(              $stats['priority_text']);
    }

    /**
     * @depends testDelete
     * @depends testStats
     */
    public function testStatsOfDeleted()
    {
        $job = $this->getAJob('buried = 1 OR reserved = 1');
        $job->delete();
        $this->tester->expectException(InvalidJobOperation::class, function() use ($job) { $job->stats(); });
    }

    public function testRelease()
    {
        $this->markTestSkipped('Can\'t unit-test release($priority, $delay) as it depends on Queue::reserve()');
    }

    public function testBury()
    {
        $job = $this->getAJob('buried = 0');
        $job->bury();
        $this->assertEquals(Job::ST_BURIED, $job->getState());
    }

    public function testBuryWithPriority()
    {
        $job = $this->getAJob();
        $job->bury($priority = 55);
        $this->assertEquals(Job::ST_BURIED, $job->getState());
        $this->assertEquals($priority, $job->stats()['priority']);
    }

    public function testKick()
    {
        $job = $this->getAJob('buried = 1');
        $job->kick();
        $this->assertEquals(Job::ST_READY, $job->getState());
    }

    public function testUnserialize()
    {
        $job = $this->getAJob();
        $packed   = serialize($job);
        $unpacked = unserialize($packed);
        $this->assertEquals($unpacked, $job);
    }

}
