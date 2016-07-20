<?php namespace Phalcon\Queue;

use Phalcon\Di;
use BadMethodCallException as BadMethod;
use Phalcon\Queue\Db\Job;
use Phalcon\Queue\Db\Model as JobModel;

require_once __DIR__ . '/../tests/unit/DbTest.php';

/**
 * Tries to mimic Phalcon's Beanstalk Queue class for low-throughput queue needs
 *
 * <code>
 * $queue = new \Phalcon\Queue\Db();
 * </code>
 *
 * @todo: implement TTR and Job::touch()
 */
class Db extends Beanstalk
{

    /** @var \Phalcon\Db\Adapter\Pdo */
    protected $connection;

    /**
     * Used for the db connection
     * @var  string
     */
    protected $diServiceKey;

    /**
     * Where to put jobs.
     * @var string
     */
    protected $using = 'default';

    /**
     * Where to get jobs from.
     * @var array
     */
    protected $watching = ['default'];

    /** Time to run (aka timeout) */
//    const OPT_TTR      = 'ttr';
    /** How long to wait before this job becomes available */
    const OPT_DELAY    = 'delay';
    const OPT_PRIORITY = 'priority';
    const OPT_TUBE     = 'tube';

    const OPTIONS = [
        self::OPT_DELAY,
//        self::OPT_TTR,
        self::OPT_PRIORITY,
        self::OPT_TUBE,
    ];

    /**
     * Queue manager constructor. Will connect upon creation.
     * By default, will look for a service called 'db'.
     * @todo implement some way to force a persistent db connection
     * @param string $diServiceKey
     */
    public function __construct($diServiceKey = 'db')
    {
        $this->diServiceKey = $diServiceKey;
        $this->connect();
    }

    /**
     * Opens a connection to the database, using dependency injection.
     * @see \Phalcon\Queue\Db::diServiceKey
     * @return boolean
     */
    public function connect() {
        if (!$this->connection) {
            $this->connection = Di::getDefault()->get($this->diServiceKey);
        }
        return true;
    }

    /**
     * Inserts jobs into the queue
     * @param mixed $data
     * @param array $options
     * @return string|bool
     */
    public function put($data, $options = [])
    {
        $payload = array_merge($options, ['body' => serialize($data)]);
        $job     = new JobModel();
        $job->save($payload);

        return $job->id;
    }

    /**
     * Reserves a job in the queue
     * @param int $timeout How long to wait while pooling for a new job
     * @param int $pool    How frequently to pool for a new job (in seconds).
     * @return bool|\Phalcon\Queue\Db\Job
     */
    public function reserve($timeout = 0, $pool = 1)
    {
    }

    /**
     * Change the active tube to put jobs on. The default tube is "default".
     * @param string $tube
     * @return string
     * @see \Phalcon\Queue\Db::using()
     */
    public function choose($tube)
    {
        return $this->using = (string)$tube;
    }

    /**
     * Change what tubes to watch for when getting jobs. The default value is "default".
     * @param string|array $tube Name of one or more tubes to watch
     * @param bool $replace If the current watch list should be replaced for this one, or just include these
     * @return string
     * @see \Phalcon\Queue\Db::watching()
     */
    public function watch($tube, $replace = false) {
        if ($replace) {
            $this->watching = (array)$tube;
        } else {
            $this->watching = array_unique(array_merge($this->watching, (array)$tube));
        }
        return $this->watching;
    }

    /**
     * Removes a tube from the list of watched tubes.
     * Can't be used to un-watch the last tube, so in this case, it will silently ignore the request.
     * @param string $tube
     * @return array The final list of watched tubes
     */
    public function ignore($tube)
    {
        if (sizeof($this->watching) > 1) {
            $this->watching = array_filter($this->watching, function($v) use ($tube) { return $v != $tube; });
        }
        return $this->watching;
    }

    /**
     * Returns what tube is currently active to put jobs on.
     * @return string
     * @see \Phalcon\Queue\Db::choose()
     * @see \Phalcon\Queue\Db::chosen()
     */
    public function using() { return $this->using; }


    /**
     * Returns what tube is currently active to put jobs on.
     * Alias of {@link \Phalcon\Queue\Db::using()}.
     * @return string
     * @see \Phalcon\Queue\Db::choose()
     * @see \Phalcon\Queue\Db::using()
     */
    public function chosen() { return $this->using(); }

    /**
     * Returns what tube(s) will be used to get jobs from.
     * @return array
     * @see \Phalcon\Queue\Db::watch()
     */
    public function watching() { return $this->watching; }

    /**
     * Get stats of the open tubes.
     * Each entry (keyed by tube) contains the following keys, pointing to the number of corresponding jobs on the
     * table:
     *   - buried (failed)
     *   - delayed (going to be ready for work only later)
     *   - ready (waiting for being worked on)
     *   - reserved (being worked on)
     *   - total (sum of all)
     * @param string $filterTube Return only data regarding a given tube
     * @return array[]
     * @todo missing "urgent" count
     */
    public function stats($filterTube = null)
    {
        $query = JobModel::query()
            ->columns([
                'tube',
                'COUNT(*) AS total',
                Job::ST_BURIED,
                Job::ST_RESERVED,
                'delay >= :timestamp: AS '.Job::ST_DELAYED,
            ])
            ->bind(['timestamp' => time()])
            ->groupBy(['tube', Job::ST_BURIED, Job::ST_RESERVED, Job::ST_DELAYED])
            ->orderBy('tube');

        if ($filterTube) {
            $query->where('tube = :tube:', ['tube' => $filterTube]);
        }

        $result = $query->execute();

        $structure = [
            Job::ST_BURIED   => 0,
            Job::ST_DELAYED  => 0,
            Job::ST_READY    => 0,
            Job::ST_RESERVED => 0,
            'total'          => 0,
        ];
        $stats     = (!$filterTube) ? ['all' => $structure] : [];
        foreach ($result->toArray() as $entry) {
            $tube  = $entry['tube'];
            $total = $entry['total'];
            unset($entry['total'], $entry['tube']);

            if (!array_key_exists($tube, $stats)) {
                $stats[$tube] = $structure;
            }

            $entry  = array_filter($entry); //leaves only the status that has a value
            $status = $entry ? array_keys($entry)[0] : Job::ST_READY; //if there's no key, it's the count for ready jobs
            $stats[$tube][$status] += $total;
            $stats[$tube]['total'] += $total;
            if (!$filterTube) {
                $stats['all'][$status] += $total;
                $stats['all']['total'] += $total;
            }
        }

        return $filterTube ? current($stats) : $stats;
    }

    /**
     * Get stats of a tube. By default, gets from the currently active tube.
     * Returns an array with the following keys:
     *   - buried (failed)
     *   - delayed (going to be ready for work only later)
     *   - ready (waiting for being worked on)
     *   - reserved (being worked on)
     *   - total (sum of all)
     * @param string $tube
     * @return array
     */
    public function statsTube($tube = null)
    {
        return $this->stats($tube?: $this->using);
    }

    /**
     * Get list of a tubes.
     * @return bool|array
     */
    public function listTubes()
    {
        $result = JobModel::query()
            ->distinct(true)
            ->columns('tube')
            ->orderBy('tube')
            ->execute()
            ->toArray();

        return array_column($result, 'tube');
    }

    /**
     * Peeks into a specific job.
     * @param int $id
     * @return null|\Phalcon\Queue\Db\Job
     */
    public function peek($id)
    {
    }

    /**
     * Inspect the next ready job.
     * @return null|\Phalcon\Queue\Db\Job
     */
    public function peekReady()
    {
        $job = JobModel::findFirst([
            'conditions' => 'tube = :tube: AND delay < :timestamp:',
            'order'      => 'id ASC',
            'bind'       => [
                'tube'      => $this->using,
                'timestamp' => time()
            ],
        ]);

        if ($job) {
            return Job::fromModel($this, $job);
        } else {
            return false;
        }
    }

    /**
     * Return the next job in the list of buried jobs
     * @return bool|\Phalcon\Queue\Db\Job
     */
    public function peekBuried()
    {
    }

    /**
     * Inspect the next delayed job.
     * @return null|\Phalcon\Queue\Db\Job
     */
    public function peekDelayed()
    {
    }

    /**
     * Kicks back into the queue $number buried jobs (at least one).
     * @param int $number
     * @return int total of actually kicked jobs
     */
    public function kick($number = 1)
    {

    }

    public function __sleep()
    {
        return ['diServiceKey','using','watching'];
    }

    public function __wakeup()
    {
        $this->connect();
    }

    public function read($length = 0) { throw new BadMethod('"read" is not a valid method in DB queues.'); }
    protected function write($data) { throw new BadMethod('"write" is not a valid method in DB queues.'); }
    public function disconnect() { throw new BadMethod('"disconnect" is not a valid method in DB queues.'); }
}
