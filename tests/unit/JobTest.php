<?php
use Phalcon\Queue\Db;
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
        $model = JobModel::findFirst($criteria?: ['order' => 'RAND()']);
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

    public function testGetBody()
    {
        $job = $this->getAJob('tube="int"');
        $this->assertSame(10, $job->getBody());
    }

    public function testStats()
    {
        $stats = $this->getAJob()->stats();
        foreach(['age','id','state','tube','delay','priority'] as $key) {
            $this->assertArrayHasKey($key, $stats, "Key $key not found on job stats");
        }
    }

    public function testDelete()
    {
        $job = $this->getAJob(1);
        $job->delete();
        $this->assertFalse($this->getAJob(1));
    }

    public function testStatsOfDeleted()
    {
        $job = $this->getAJob();
        $job->delete();
        $this->tester->expectException(BadMethodCallException::class, function() use ($job) { $job->stats(); });
    }

    public function testRelease()
    {
        $this->markTestSkipped('Can\'t unit-test release($priority, $delay) as it depends on Queue::reserve()');
    }

    public function testBury()
    {
        $job = $this->getAJob();
        $job->bury();
        $this->assertEquals(Job::ST_BURIED, $job->stats()['state']);
    }

    public function testBuryWithPriority()
    {
        $job = $this->getAJob();
        $job->bury($priority = 55);
        $this->assertEquals(Job::ST_BURIED, $job->stats()['state']);
        $this->assertEquals($priority, $job->stats()['priority']);
    }

    public function testKick()
    {
        $job = $this->getAJob();
        $job->bury();
        $job->kick();
        $this->assertEquals(Job::ST_READY, $job->stats()['state']);
    }

    public function testUnserialize()
    {
        $job = $this->getAJob();
        $packed   = serialize($job);
        $unpacked = unserialize($packed);
        $this->assertEquals($job->getModel(), $unpacked->getModel()); //TODO: could be taken out if the next line also does this
        $this->assertEquals($unpacked, $job);
    }

}
