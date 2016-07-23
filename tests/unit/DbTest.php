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

    /**
     * @depends testPut
     * @depends testPeek
     */
    public function testChoose()
    {
        $this->queue->choose($tube = 'special');
        $id  = $this->queue->put('special');
        $job = $this->queue->peek($id);
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($tube, $job->stats()['tube']);
    }

    /** @depends testReserve */
    public function testWatch()
    {
        //gets from the default tube
        $this->assertEquals($this->queue->watching(), [self::TUBE_DEFAULT]);
        $job = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals(self::TUBE_DEFAULT, $job->stats()['tube']);

        //watches "array" tube only
        $this->queue->watch(self::TUBE_ARRAY, true);
        $this->assertEquals($this->queue->watching(), [self::TUBE_ARRAY]);
        $array = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $array);
        $this->assertEquals(self::TUBE_ARRAY, $array->stats()['tube']);

        //watches also "int" tube
        $this->queue->watch(self::TUBE_INT);
        $this->assertEquals($this->queue->watching(), [self::TUBE_ARRAY, self::TUBE_INT]);
        $array = $this->queue->reserve(); //reserves this one so peek() returns another one later
        $int   = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $array);
        $this->assertInstanceOf(Job::class, $int);
        $this->assertEquals(self::TUBE_ARRAY, $array->stats()['tube']);
        $this->assertEquals(self::TUBE_INT, $int->stats()['tube']);
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

    /** @depends testPeek */
    public function testPut()
    {
        $id = $this->queue->put($body = 'test');
        $this->assertInternalType('int', $id);
        $this->assertGreaterThan(0, $id);
        $job = $this->queue->peek($id);
        $this->assertEquals($body, $job->getBody());
    }

    /** @depends testPut */
    public function testPutComplexTypes()
    {
        //FIXME: read about data providers
        $bodies = [
            'array'  => [1 => 'a', 'b' => false],
            'object' => new DateTime('now')
        ];

        foreach ($bodies as $type => $body) {
            $id = $this->queue->put($body);
            $this->assertInternalType('int', $id, "Body of $type is not int");
            $this->assertGreaterThan(0, $id, "ID of $type seems weird");
            $this->assertEquals($body, $this->queue->peek($id)->getBody(), "Body of $type failed on comparison");
        }
    }

    /**
     * @depends testPut
     * @depends testPeek
     */
    public function testPutPriority()
    {
        $body     = 'URGENT';
        $priority = Job::PRIORITY_HIGHEST;

        //puts the job correctly
        $id = $this->queue->put($body, [Db::OPT_PRIORITY => $priority]);
        $this->assertInternalType('int', $id);

        //verifies if the job information is correct
        $job   = $this->queue->peek($id);
        $stats = $job->stats();
        $this->assertEquals($body, $job->getBody());
        $this->assertEquals($priority, $stats['priority']);
    }

    /**
     * @depends testPut
     * @depends testPeek
     */
    public function testPutDelay()
    {
        $body  = 'delayed';
        $start = time();
        $delay = 2;

        //puts the job correctly
        $id = $this->queue->put($body, [Db::OPT_DELAY => $delay]);
        $this->assertInternalType('int', $id);

        //verifies if the job information is correct
        $job   = $this->queue->peek($id);
        $stats = $job->stats();
        $this->assertEquals($body, $job->getBody());
        $this->assertEquals($delay, $stats['delay']);
        $this->assertEquals($start+$delay, $stats['delayed_until']);
    }

    /**
     * @depends testPutPriority
     * @depends testPutDelay
     */
    public function testPutAllTogetherNow()
    {
        //verifies all properties are being correctly set all at once - their behaviour is verified in the other tests
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

    /**
     * @depends testPut
     * @depends testChoose
     * @depends testWatch
     * @depends testPeek
     * @depends testReserve
     * @depends testReserveTimeout
     */
    public function testReserveDelayed()
    {
        $body  = 'specially delayed';
        $delay = 2;
        $tube  = 'special'; //uses a special tube so we have no jobs in front of this
        $this->queue->choose($tube);
        $this->queue->watch($tube, true);

        $this->queue->put($body, [Db::OPT_DELAY => $delay]);
        $this->assertNull($this->queue->reserve());     //gets nothing for now
        $job = $this->queue->reserve($delay);           //then, after two seconds...
        $this->assertInstanceOf(Job::class, $job);      //...it gets a job...
        $this->assertEquals(0, $job->stats()['delay']); //...with the correct values :D
        $this->assertEquals($body, $job->getBody());
    }

    /** @depends testReserve */
    public function testReservePrioritized()
    {
        $this->queue->put($urgent = 'URGENT', [Db::OPT_PRIORITY => Job::PRIORITY_HIGHEST]);
        $this->queue->put($almost = 'ALMOST', [Db::OPT_PRIORITY => Job::PRIORITY_HIGHEST + 1]);
        $this->assertEquals($urgent, $this->queue->reserve()->getBody());
        $this->assertEquals($almost, $this->queue->reserve()->getBody());
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
}
