<?php
use Phalcon\Queue\Db;

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

    protected function _after() { }

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
        $tubes = $this->queue->listTubes();
        $correct_tubes = static::$tubes;
        sort($correct_tubes);
        $this->tester->assertEquals($correct_tubes, $tubes, 'not all tubes were found');
    }
    
}