<?php
use Phalcon\Queue\Db;
use Phalcon\Queue\Db\Job;

class DbTest extends \Codeception\TestCase\Test
{
    const TUBE_DEFAULT= 'default';
    const TUBE_ARRAY  = 'array';
    const TUBE_INT    = 'int';
    const TUBE_JSON   = 'json';
    const TUBE_STRING = self::TUBE_DEFAULT;

    public static $tubes = [
        self::TUBE_ARRAY,
        self::TUBE_DEFAULT,
        self::TUBE_INT,
        self::TUBE_JSON,
    ];

    public static $stats = [
        'all'              => [
            'buried'   => 1,
            'delayed'  => 1,
            'ready'    => 6,
            'reserved' => 1,
            'urgent'   => 1,
            'total'    => 10,
        ],
        self::TUBE_ARRAY   => [
            'buried'   => 0,
            'delayed'  => 0,
            'ready'    => 1,
            'reserved' => 0,
            'urgent'   => 0,
            'total'    => 1,
        ],
        self::TUBE_DEFAULT => [
            'buried'   => 1,
            'delayed'  => 1,
            'ready'    => 3,
            'reserved' => 1,
            'urgent'   => 1,
            'total'    => 7,
        ],
        self::TUBE_INT     => [
            'buried'   => 0,
            'delayed'  => 0,
            'ready'    => 1,
            'reserved' => 0,
            'urgent'   => 0,
            'total'    => 1,
        ],
        self::TUBE_JSON    => [
            'buried'   => 0,
            'delayed'  => 0,
            'ready'    => 1,
            'reserved' => 0,
            'urgent'   => 0,
            'total'    => 1,
        ],
    ];

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

    public function testInstance()
    {
        $this->assertInstanceOf(Db::class, $this->queue);
    }

    /** @db empty */
    public function testListEmptyTubes()
    {
        $tubes = $this->queue->listTubes();
        $this->assertEquals([], $tubes, 'there should be no tubes in the database');
    }

    public function testListAllTubes()
    {
        $tubes = $this->queue->listTubes();
        $this->assertEquals(self::$tubes, $tubes, 'not all tubes were found', 0, 2, true);
    }

    public function testStats()
    {
        $stats = $this->queue->stats();
        $this->assertEquals(self::$stats, $stats);
    }

    public function testStatsTube()
    {
        $stats = $this->queue->statsTube(self::TUBE_DEFAULT);
        $this->assertEquals(self::$stats[self::TUBE_DEFAULT], $stats);
    }

    /** @depends testPut */
    public function testChoose()
    {
    }

    /** @depends testReserve */
    public function testWatch()
    {
        //gets from the default tube
        $this->assertEquals($this->queue->watching(), [self::TUBE_DEFAULT]);
        $job = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertAttributeEquals(self::TUBE_DEFAULT, 'tube', $job);

        //watches "array" tube only
        $this->queue->watch(self::TUBE_ARRAY, true);
        $this->assertEquals($this->queue->watching(), [self::TUBE_ARRAY]);
        $array = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $array);
        $this->assertAttributeEquals(self::TUBE_ARRAY, 'tube', $array);

        //watches also "int" tube
        $this->queue->watch(self::TUBE_INT);
        $this->assertEquals($this->queue->watching(), [self::TUBE_ARRAY, self::TUBE_INT]);
        $array = $this->queue->reserve(); //reserves this one so peek() returns another one later
        $int   = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $array);
        $this->assertInstanceOf(Job::class, $int);
        $this->assertAttributeEquals(self::TUBE_ARRAY, 'tube', $array);
        $this->assertAttributeEquals(self::TUBE_INT, 'tube', $int);
    }

    /** @depends testWatch */
    public function testIgnore()
    {
        //default tube is the only being watched in the beginning
        $this->assertEquals([self::TUBE_DEFAULT], $this->queue->watching());

        //adds INT and makes sure it's there
        $this->queue->watch(self::TUBE_INT);
        $this->assertEquals([self::TUBE_DEFAULT, self::TUBE_INT], $this->queue->watching());

        //removes default and make sure it's gone
        $watching = $this->queue->ignore(self::TUBE_DEFAULT);
        $this->assertEquals([self::TUBE_INT], $watching);
        $this->assertEquals([self::TUBE_INT], $this->queue->watching());

        //tries to remove int as well, silently ignored
        $watching = $this->queue->ignore(self::TUBE_INT);
        $this->assertEquals([self::TUBE_INT], $watching);
        $this->assertEquals([self::TUBE_INT], $this->queue->watching());
    }

    public function testPut()
    {
        $this->markTestIncomplete();
    }

    public function testPriority()
    {
        $this->markTestIncomplete('should put a job with priority and see it getting in front of newer jobs when reserving');
    }

    public function testPutDelay()
    {
        $this->markTestIncomplete();
    }

    /** @depends testPeekReady */
    public function testReserve()
    {
        //reserves one
        $job = $this->queue->reserve();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals(Job::ST_RESERVED, $job->getState());

        //another reserve should return a different job
        $other = $this->queue->reserve();
        $this->assertNotEquals($job, $other);
    }

    /** @depends testReserve */
    public function testReserveEmpty()
    {
        //chooses a tube that has only one job available and tries to reserve two
        $this->queue->watch(self::TUBE_ARRAY, true);
        $this->queue->reserve();
        $this->assertNull($this->queue->reserve());
    }

    /** @depends testReserve */
    public function testReserveTimeout()
    {
        //chooses a tube that has only one job available, and reserves it
        $this->queue->watch(self::TUBE_ARRAY, true);
        $job = $this->queue->reserve();

        //tests timeout on a reserve operation
        $time = time();
        $nothing = $this->queue->reserve($timeout = 2);
        $this->assertNull($nothing);
        $this->assertEquals(time(), $time+$timeout, null, 1);
    }

    /** @depends testReserve */
    public function testReserveDelayed()
    {
        $this->markTestIncomplete('should put a delayed job and wait a bit until it gets ready');
    }

    /** @depends testReserve */
    public function testReservePrioritized()
    {
        $this->markTestIncomplete('should reserve three jobs with different priorities and see them in order');
    }

    public function testRelease()
    {
        $this->markTestIncomplete('should reserve a job and release it back and see it\'s status. also, test $priority and $delay arguments');
    }

    public function testPeek()
    {
        $job = $this->queue->peek(1);
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals(1, $job->getId());
    }

    public function testPeekReady()
    {
        $job  = $this->queue->peekReady();
        $same = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertInstanceOf(Job::class, $same);
        $this->assertEquals($job->getId(), $same->getId());
    }

    public function testPeekBuried()
    {
        $job = $this->queue->peekBuried();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($job->getBody(), 'buried');
        $this->assertEquals($job->getState(), Job::ST_BURIED);
    }

    public function testPeekDelayed()
    {
        $job = $this->queue->peekDelayed();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($job->getBody(), 'delayed until later');

        $stats = $job->stats();
        $this->assertEquals($stats['state'], Job::ST_DELAYED);
        $this->assertGreaterThan(time(), $stats['delayed_until']);
        $this->assertGreaterThan(0, $stats['delay']);
    }

    //TODO: implement workflow test
    //TODO: review Beanstalk doc to see if there's any missing operation or option: https://github.com/earl/beanstalkc/blob/master/TUTORIAL.mkd
    public function testWorkflow()
    {
        $this->markTestIncomplete(<<<'TEXT'
Missing test of the complete workflow, as the Beanstalk doc says:

     put with delay               release with delay
    ----------------> [DELAYED] <------------.
                        |                    |
                        | (time passes)      |
                        |                    |
     put                v       reserve      |        delete
    -----------------> [READY] ---------> [RESERVED] --------> *poof*
                       ^  ^                |   |
                       |   \    release    |   |
                       |    `--------------'   |
                       |                       |
                       | kick                  |
                       |                       |
                       |       bury            |
                    [BURIED] <-----------------'
                       |
                       |  delete
                        `--------> *poof*

Pay special attention to not being able to operate on ready jobs without reserving them first!!!!!! 
TEXT
);

    }
}
