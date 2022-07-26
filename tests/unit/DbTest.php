<?php

use Phalcon\Queue\Db;
use Phalcon\Queue\Db\Job;

class DbTest extends \Codeception\TestCase\Test
{
    public const TUBE_DEFAULT = 'default';

    public const TUBE_ARRAY = 'array';

    public const TUBE_INT = 'int';

    public const TUBE_JSON = 'json';

    public const TUBE_STRING = self::TUBE_DEFAULT;

    public static $tubes = [
        self::TUBE_ARRAY,
        self::TUBE_DEFAULT,
        self::TUBE_INT,
        self::TUBE_JSON,
    ];

    public static $stats = [
        'all' => [
            'buried' => 3,
            'delayed' => 1,
            'name' => 'all',
            'ready' => 6,
            'reserved' => 1,
            'urgent' => 1,
            'total' => 12,
        ],
        self::TUBE_ARRAY => [
            'buried' => 0,
            'delayed' => 0,
            'name' => self::TUBE_ARRAY,
            'ready' => 1,
            'reserved' => 0,
            'urgent' => 0,
            'total' => 1,
        ],
        self::TUBE_DEFAULT => [
            'buried' => 3,
            'delayed' => 1,
            'name' => self::TUBE_DEFAULT,
            'ready' => 3,
            'reserved' => 1,
            'urgent' => 1,
            'total' => 9,
        ],
        self::TUBE_INT => [
            'buried' => 0,
            'delayed' => 0,
            'name' => self::TUBE_INT,
            'ready' => 1,
            'reserved' => 0,
            'urgent' => 0,
            'total' => 1,
        ],
        self::TUBE_JSON => [
            'buried' => 0,
            'delayed' => 0,
            'name' => self::TUBE_JSON,
            'ready' => 1,
            'reserved' => 0,
            'urgent' => 0,
            'total' => 1,
        ],
    ];

    /** @var Db */
    protected $queue;

    protected function _before()
    {
        $this->queue = new Db();
        $annotations = $this->getAnnotations()['method'];

        //reads the @db annotation and see if this test needs a clean table instead
        if (isset($annotations['db'])) {
            switch ($annotations['db'][0]) {
                case 'empty':
                    $this->getModule('Db')->_getDbh()->exec('DELETE FROM jobs');
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
        // Annotations not working
        $this->getModule('Db')->_getDbh()->exec('DELETE FROM jobs');

        $tubes = $this->queue->listTubes();
        $this->assertEquals([], $tubes, 'there should be no tubes in the database');
    }

    public function testListAllTubes()
    {
        $tubes = $this->queue->listTubes();
        $this->assertEqualsCanonicalizing(self::$tubes, $tubes, 'not all tubes were found');
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
        $id = $this->queue->put('special');
        $job = $this->queue->peek($id);
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($tube, $job->stats()->tube);

        $this->assertEquals($tube, $this->queue->using());
        $this->assertEquals($tube, $this->queue->chosen());
    }

    public function testWatch()
    {
        //gets from the default tube
        $this->assertEquals($this->queue->watching(), [self::TUBE_DEFAULT]);
        $job = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals(self::TUBE_DEFAULT, $job->stats()->tube);

        //watches "array" tube only
        $this->queue->watch(self::TUBE_ARRAY, true);
        $this->assertEquals($this->queue->watching(), [self::TUBE_ARRAY]);
        $array = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $array);
        $this->assertEquals(self::TUBE_ARRAY, $array->stats()->tube);

        //watches also "int" tube
        $this->queue->watch(self::TUBE_INT);
        $this->assertEquals($this->queue->watching(), [self::TUBE_ARRAY, self::TUBE_INT]);
        $array = $this->queue->reserve(0); //reserves this one so peek() returns another one later
        $int = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $array);
        $this->assertInstanceOf(Job::class, $int);
        $this->assertEquals(self::TUBE_ARRAY, $array->stats()->tube);
        $this->assertEquals(self::TUBE_INT, $int->stats()->tube);
    }

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
        $id = $this->queue->put($body = 'test');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        $job = $this->queue->peek($id);
        $this->assertEquals($body, $job->getBody());
    }

    public function testPutComplexTypes()
    {
        //FIXME: read about data providers
        $bodies = [
            'array' => [
                1 => 'a',
                'b' => false,
            ],
            'object' => new DateTime(
                'now'
            ),
        ];

        foreach ($bodies as $type => $body) {
            $id = $this->queue->put($body);
            $this->assertIsInt($id, "Body of $type is not int");
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
        $body = 'URGENT';
        $priority = Job::PRIORITY_HIGHEST;

        //puts the job correctly
        $id = $this->queue->put($body, [
            Db::OPT_PRIORITY => $priority,
        ]);
        $this->assertIsInt($id);

        //verifies the job information is correct
        $job = $this->queue->peek($id);
        $this->assertEquals($body, $job->getBody());
        $this->assertEquals($priority, $job->stats()->priority);
    }

    /**
     * @depends testPut
     * @depends testPeek
     */
    public function testPutPriorityLimits()
    {
        $id = $this->queue->put('x', [
            Db::OPT_PRIORITY => Job::PRIORITY_HIGHEST,
        ]);
        $this->assertEquals(Job::PRIORITY_HIGHEST, $this->queue->peek($id)->stats()->priority);

        $id = $this->queue->put('x', [
            Db::OPT_PRIORITY => Job::PRIORITY_LOWEST,
        ]);
        $this->assertEquals(Job::PRIORITY_LOWEST, $this->queue->peek($id)->stats()->priority);
    }

    /**
     * @depends testPut
     * @depends testPeek
     */
    public function testPutDelay()
    {
        $body = 'delayed';
        $start = time();
        $delay = 2;

        //puts the job correctly
        $id = $this->queue->put($body, [
            Db::OPT_DELAY => $delay,
        ]);
        $this->assertIsInt($id);

        //verifies if the job information is correct
        $job = $this->queue->peek($id);
        $stats = $job->stats();
        $this->assertEquals($body, $job->getBody());
        $this->assertEquals($delay, $stats->delay);
        $this->assertEquals($start + $delay, $stats->delayedUntil);
    }

    /**
     * @depends testPutPriority
     * @depends testPutDelay
     * @depends testPeek
     */
    public function testPutAllTogetherNow()
    {
        //verifies all properties are being correctly set all at once - their behaviour is verified in the other tests
        $body = 'all together now';
        $delay = 10;
        $priority = Job::PRIORITY_LOWEST;

        $id = $this->queue->put($body, [
            Db::OPT_DELAY => $delay,
            Db::OPT_PRIORITY => $priority,
        ]);
        $job = $this->queue->peek($id);
        $stats = $job->stats();
        $this->assertEquals($body, $job->getBody());
        $this->assertEquals($delay, $stats->delay);
        $this->assertEquals($priority, $stats->priority);
    }

    public function testReserve()
    {
        //reserves one
        $job = $this->queue->reserve(0);
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals(Job::ST_RESERVED, $job->getState());

        //another reserve should return a different job
        $other = $this->queue->reserve(0);
        $this->assertNotEquals($job, $other);
    }

    public function testReserveEmpty()
    {
        //chooses a tube that has only one job available and tries to reserve two
        $this->queue->watch(self::TUBE_ARRAY, true);
        $this->queue->reserve(0);
        $this->assertFalse($this->queue->reserve(0));
    }

    public function testReserveTimeout()
    {
        //chooses a tube that has only one job available, and reserves it
        $this->queue->watch(self::TUBE_ARRAY, true);
        $this->queue->reserve(0);

        //tests timeout on a reserve operation
        $time = time();
        $nothing = $this->queue->reserve($timeout = 2);
        $this->assertFalse($nothing);
        $this->assertEqualsWithDelta(time(), $time + $timeout, 0.2, "Reserved Timtout is ok");
    }

    public function testReserveDelayed()
    {
        $body = 'specially delayed';
        $delay = 2;
        $tube = 'special'; //uses a special tube so we have no jobs in front of this
        $this->queue->choose($tube);
        $this->queue->watch($tube, true);

        $this->queue->put($body, [
            Db::OPT_DELAY => $delay,
        ]);
        $this->assertFalse($this->queue->reserve(0));     //gets nothing for now
        $job = $this->queue->reserve($delay);           //then, after two seconds...
        $this->assertInstanceOf(Job::class, $job);      //...it gets a job...
        $this->assertEquals(0, $job->stats()->delay);   //...with the correct values :D
        $this->assertEquals($body, $job->getBody());
    }

    /**
     * @db empty
     */
    public function testReservePrioritized()
    {
        // annotations not working
        $this->getModule('Db')->_getDbh()->exec('DELETE FROM jobs');

        $this->queue->put($urgent = 'URGENT', [
            Db::OPT_PRIORITY => Job::PRIORITY_HIGHEST,
        ]);
        $this->queue->put($almost = 'ALMOST', [
            Db::OPT_PRIORITY => Job::PRIORITY_HIGHEST + 1,
        ]);
        $this->queue->put($normal = 'NORMAL', [
            Db::OPT_PRIORITY => Job::PRIORITY_DEFAULT,
        ]);
        $this->queue->put($medium = 'MEDIUM', [
            Db::OPT_PRIORITY => Job::PRIORITY_MEDIUM,
        ]);
        $this->queue->put($lowest = 'LOWEST', [
            Db::OPT_PRIORITY => Job::PRIORITY_LOWEST,
        ]);
        $this->assertEquals($urgent, $this->queue->reserve(0)->getBody());
        $this->assertEquals($almost, $this->queue->reserve(0)->getBody());
        $this->assertEquals($normal, $this->queue->reserve(0)->getBody());
        $this->assertEquals($medium, $this->queue->reserve(0)->getBody());
        $this->assertEquals($lowest, $this->queue->reserve(0)->getBody());
    }

    public function testReserveLowPriority()
    {
        $this->queue->put($common = 'common priority', [
            Db::OPT_PRIORITY => Job::PRIORITY_DEFAULT,
        ]);
    }

    public function testProcess()
    {
        //asserts it passes through all available jobs in "default"
        $total = 0;
        $stats = self::$stats[self::TUBE_DEFAULT];
        $available = $stats['ready'] + $stats['urgent'];
        $this->queue->process(function () use (&$total) {
            ++$total;
            return true;
        }, 1, null, 0);
        $this->assertEquals($available, $total, 'Incorrect number of jobs processed in "default" tube');
    }

    public function testProcessLimit()
    {
        $total = 0;
        $this->queue->process(function () use (&$total) {
            ++$total;
            return true;
        }, 1, $limit = 2);
        $this->assertEquals($limit, $total, 'Incorrect number of limited jobs processed in "default" tube');
    }

    public function testProcessDelete()
    {
        //asserts the job body is correctly fetch and the job is deleted correctly
        $id = 0;
        $this->queue->watch('array', true);
        $this->queue->process(function ($body, Job $job) use (&$id) {
            $this->assertIsArray($body);
            $this->assertInstanceOf(Job::class, $job);
            $id = $job->getId();
            return true;
        }, 1, null, 0);
        $this->assertFalse($this->queue->peek($id), 'array job processed was not deleted');
    }

    public function testProcessBury()
    {
        $this->queue->watch('int', true);
        $this->queue->process(function ($body, Job $job) use (&$id) {
            $id = $job->getId();
            return false;
        }, 1, null, 0);
        $this->assertEquals(Job::ST_BURIED, $this->queue->peek($id)->getState(), 'Job was not buried on FALSE');
    }

    public function testProcessRelease()
    {
        $count = 0;
        $this->queue->watch('int', true);
        $this->queue->process(function ($body, Job $job) use (&$id, &$count) {
            if ($count == 0) {
                $id = $job->getId();
                ++$count;
            }
        }, 1, 1);
        $this->assertEquals(Job::ST_READY, $this->queue->peek($id)->getState(), 'Job was not released on NULL');
    }

    public function testProcessCallables()
    {
        $this->queue->watch('json');
        $worker = function ($body) {
            $this->assertNotEmpty($body);
        };
        $this->queue->process($worker, 1, 1, 0);
        $this->queue->process([$this, 'workerForProcessTest'], 1, 1, 0);
    }

    public function workerForProcessTest($body, Job $job)
    {
        $this->assertNotEmpty($body);
    }

    public function testPeek()
    {
        $job = $this->queue->peek(1);
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals(1, $job->getId());

        $this->assertFalse($this->queue->peek(0));
    }

    public function testPeekReady()
    {
        $job = $this->queue->peekReady();
        $same = $this->queue->peekReady();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertInstanceOf(Job::class, $same);
        $this->assertEquals($job->getId(), $same->getId());
    }

    public function testPeekBuried()
    {
        $job = $this->queue->peekBuried();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals('buried', $job->getBody());
        $this->assertEquals(Job::ST_BURIED, $job->getState());
    }

    public function testPeekDelayed()
    {
        $job = $this->queue->peekDelayed();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals('delayed until later', $job->getBody());

        $stats = $job->stats();
        $this->assertEquals(Job::ST_DELAYED, $stats->state);
        $this->assertGreaterThan(time(), $stats->delayedUntil);
        $this->assertGreaterThan(0, $stats->delay);
    }

    public function testKick()
    {
        //kicks all buried jobs but one, and sees if the updated stats reflect this
        $this->queue->kick($this->queue->statsTube()['buried'] - 1);
        $this->assertEquals(1, $this->queue->statsTube()['buried']);

        //then, sees if kicking more than we currently have will also behave as expected
        $kicked = $this->queue->kick(4);
        $this->assertEquals(1, $kicked);
    }
}
