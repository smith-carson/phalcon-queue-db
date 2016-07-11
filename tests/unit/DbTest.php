<?php
use Phalcon\Queue\Db;
use Phalcon\Queue\Db\Job;

class DbTest extends \Codeception\TestCase\Test
{
    const TUBE_JSON   = 'json';
    const TUBE_ARRAY  = 'array';
    const TUBE_STRING = 'default';
    const TUBE_INT    = 'int';

    public static $tubes = [
        self::TUBE_JSON,
        self::TUBE_ARRAY,
        self::TUBE_STRING,
        self::TUBE_INT,
    ];

    /** @var \UnitTester */
    protected $tester;

    /** @var  Db */
    protected $queue;

    protected function _before()
    {
        $this->queue = new Db();
        $annotations = $this->getAnnotations()['method'];

        //reads the @db annotation and see if this test needs a clean table instead
        if (isset($annotations['db'])) {
            switch ($annotations['db'][0]) {
                case 'empty':
                    /** @var \PDO $pdo */
                    $pdo = $this->getModule('Db')->dbh;
                    $pdo->exec('DELETE FROM jobs');
                    break;
            }
        }
    }

    protected function _after()
    {
    }

    public function testInstance()
    {
        $this->tester->assertInstanceOf(Db::class, $this->queue);
    }

    /** @db empty */
    public function testListEmptyTubes()
    {
        $tubes = $this->queue->listTubes();
        $this->tester->assertEquals([], $tubes, 'there should be no tubes in the database');
    }

    public function testListAllTubes()
    {
        $tubes         = $this->queue->listTubes();
        $correct_tubes = static::$tubes;
        sort($correct_tubes);
        $this->tester->assertEquals($correct_tubes, $tubes, 'not all tubes were found');
    }

    public function testStats()
    {
        $stats = $this->queue->stats();
        $this->assertEquals([
            'all' => [
                'active'   => 5,
                'buried'   => 1,
                'delayed'  => 1,
                'reserved' => 1,
                'total'    => 8,
            ],
            'array' => [
                'active'   => 1,
                'buried'   => 0,
                'delayed'  => 0,
                'reserved' => 0,
                'total'    => 1,
            ],
            'default' => [
                'active'   => 2,
                'buried'   => 1,
                'delayed'  => 1,
                'reserved' => 1,
                'total'    => 5,
            ],
            'int' => [
                'active'   => 1,
                'buried'   => 0,
                'delayed'  => 0,
                'reserved' => 0,
                'total'    => 1,
            ],
            'json' => [
                'active'   => 1,
                'buried'   => 0,
                'delayed'  => 0,
                'reserved' => 0,
                'total'    => 1,
            ],
        ], $stats);
    }

    public function testChoose()
    {
        //gets from the default tube
        $job = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertAttributeEquals('default', 'tube', $job);

        //changes into "array" tube
        $this->queue->choose('array');
        $array = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $array);
        $this->assertAttributeEquals('array', 'tube', $job);

        //and into "int" tube using different method
        $this->queue->watch('int');
        $array = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $array);
        $this->assertAttributeEquals('int', 'tube', $job);
    }

    public function testStatsTube()
    {
        $stats = $this->queue->statsTube('default');
        $this->assertEquals([
            'active'   => 2,
            'buried'   => 1,
            'delayed'  => 1,
            'reserved' => 1,
        ], $stats);
    }

    public function testReserve()
    {
        $job = $this->queue->reserve();
        $this->tester->assertInstanceOf(Job::class, $job);
    }

    public function testReserveTtr()
    {
        $job   = $this->queue->reserve(5);
        $other = $this->queue->reserve();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertInstanceOf(Job::class, $other);
        $this->assertNotEquals($job->getId(), $other->getId());
        sleep(6); //to wait for the job to get released again
        $again = $this->queue->reserve();
        $this->assertInstanceOf(Job::class, $again);
        $this->assertEquals($job->getId(), $again->getId());
    }

    public function testPeek()
    {
        $job  = $this->queue->peekReady();
        $same = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertInstanceOf(Job::class, $same);
        $this->assertEquals($job->getId(), $same->getId());
    }

    public function testBuried()
    {
        $job = $this->queue->peekBuried();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($job->getBody(), 'buried');
    }
}
