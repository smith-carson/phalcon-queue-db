<?php namespace Phalcon\Queue;

use BadMethodCallException as BadMethod;
use Phalcon\Di;
use Phalcon\Queue\Db\InvalidJobOperationException;
use Phalcon\Queue\Db\Job;
use Phalcon\Queue\Db\Model as JobModel;

/**
 * Tries to mimic Phalcon's Beanstalk Queue class for low-throughput queue needs.
 *
 * <code>
 * $queue = new \Phalcon\Queue\Db();
 * $queue->choose('tube')->put('body');
 * $queue->watch('tube')->process(function($body) { return $works = doStuff($body); });
 * </code>
 *
 * @todo: implement TTR and Job::touch()
 */
class Db extends Beanstalk
{

    /** @var \Phalcon\Db\Adapter\Pdo */
    protected $connection;

    /**
     * Used for the db connection.
     * @var  string
     */
    protected $diServiceKey;

    /**
     * Where to put jobs.
     * @var string
     */
    protected $using = self::DEFAULT_TUBE;

    /**
     * Where to get jobs from.
     * @var array
     */
    protected $watching = [self::DEFAULT_TUBE];

    /** Time to run (aka timeout) */
//    const OPT_TTR      = 'ttr';
    /** How many seconds to wait before this job becomes available */
    const OPT_DELAY    = 'delay';
    const OPT_PRIORITY = 'priority';

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
     * @return bool
     */
    public function connect()
    {
        if (!$this->connection) {
            $this->connection = Di::getDefault()->get($this->diServiceKey);
        }
        return true;
    }

    /**
     * Inserts jobs into the queue.
     * @param mixed $data
     * @param array $options
     * @throws InvalidJobOperationException In case there's an error with the put operation
     * @return string|bool
     */
    public function put($data, array $options = null)
    {
        if (!$options) {
            $options = [];
        }

        if (isset($options[self::OPT_DELAY])) { //delay is given in secs, but stored in the database as timestamp to run
            $options[self::OPT_DELAY] += time();
        }

        $payload = array_merge($options, [
            'body' => serialize($data),
            'tube' => $this->using,
        ]);

        $job   = new JobModel();
        if ($job->save($payload)) {
            return (int) $job->id;
        } else {
            $messages = implode(' / ', $job->getMessages());
            throw new InvalidJobOperationException($messages, null);
        }
    }

    protected function simpleReserve()
    {
        if ($job = $this->peekReady()) {
            $job->getModel()->update(['reserved' => 1]);
            return $job;
        } else {
            return false;
        }
    }

    /**
     * Reserves a job in the queue.
     * @param int   $timeout How much time to spend in this function while pooling for jobs
     * @param float $delay   How long to wait while pooling for a new job before returning
     * @return bool|\Phalcon\Queue\Db\Job
     */
    public function reserve($timeout = null, $delay = 1)
    {
        //we only need to calculate the ending time if there's a timeout requirement
        $willPool = $timeout !== 0;
        $timeout  = $timeout?: PHP_INT_MAX;
        $end      = time() + $timeout;
        $udelay   = $delay * 1000;

        //while we got no job and there's need to pool and we haven't timed out, sleep and try to get a new job
        while (!($job = $this->simpleReserve()) && $willPool && time() + $delay <= $end) {
            usleep($udelay*1000);
        }

        //got it! return
        return $job;
    }

    /**
     * Takes a callable and uses it to work through all jobs.
     * Releases the job if the worker returns nothing (null); if truthy, deletes it (meaning the job finished
     * successfully), and if returns falsy, buries the job.
     *
     * <code>
     * $queue->process(function($body, $job) uses ($mailer) {
     *     $worked = $mailer->to($body['to'])->send($body['message']);
     *     return $worked;
     * });
     * </code>
     *
     * @param callable $worker  Something to process a job. Signature: function(mixed $worker, Job $job):bool
     * @param int      $delay    How long to wait until pooling for new jobs, in seconds.
     * @param int      $limit   Limit of jobs to process before exiting. Defaults to a very, very high value.
     * @param int      $timeout How much time to spend processing/pooling jobs (won't halt running jobs).
     * @return array Stats of the processed jobs: number of "success", "failure" and "skipped" jobs.
     */
    public function process(callable $worker, $delay = 1, $limit = null, $timeout = null)
    {
        $limit = $limit?: PHP_INT_MAX;

        $processed = 0;
        $stats     = [
            'success' => 0,
            'failure' => 0,
            'skipped' => 0,
            'delayed' => 0,
        ];

        while ($processed++ < $limit && ($job = $this->reserve($timeout, $delay))) {
            $result = $worker($job->getBody(), $job);
            if ($result === true) {
                $job->delete();
                ++$stats['success'];
            } elseif ($result === null) {
                $job->release();
                ++$stats['skipped'];
            } elseif (is_int($result)) { // delay number of seconds
                $job->release(0, $result);
                ++$stats['delayed'];
            } else {
                $job->bury();
                ++$stats['failure'];
            }
            if ($this->connection->isUnderTransaction()) {
                $this->connection->commit();
            }
        }

        return $stats;
    }

    /**
     * Change the active tube to put jobs on. The default tube is "default".
     * @param string $tube
     * @return self
     * @see \Phalcon\Queue\Db::using()
     */
    public function choose($tube)
    {
        $this->using = (string) $tube;
        return $this;
    }

    /**
     * Change what tubes to watch for when getting jobs. The default value is "default".
     * @param string|array $tube    Name of one or more tubes to watch
     * @param bool         $replace If the current watch list should be replaced for this one, or just include these
     * @return self
     * @see \Phalcon\Queue\Db::watching()
     */
    public function watch($tube, $replace = false)
    {
        if ($replace) {
            $this->watching = (array) $tube;
        } else {
            //merges, throws away repeated values, and re-indexes
            $this->watching = array_values(array_unique(array_merge($this->watching, (array) $tube)));
        }

        return $this;
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
            $this->watching = array_values(//array_values() reindexes the filtered array
                array_filter($this->watching, function ($v) use ($tube) {
                    return $v != $tube;
                })
            );
        }
        return $this->watching;
    }

    /**
     * Returns what tube is currently active to put jobs on.
     * @return string
     * @see \Phalcon\Queue\Db::choose()
     * @see \Phalcon\Queue\Db::chosen()
     */
    public function using()
    {
        return $this->using;
    }

    /**
     * Returns what tube is currently active to put jobs on.
     * Alias of {@link \Phalcon\Queue\Db::using()}.
     * @return string
     * @see \Phalcon\Queue\Db::choose()
     * @see \Phalcon\Queue\Db::using()
     */
    public function chosen()
    {
        return $this->using();
    }

    /**
     * Returns what tube(s) will be used to get jobs from.
     * @return array
     * @see \Phalcon\Queue\Db::watch()
     */
    public function watching()
    {
        return $this->watching;
    }

    /**
     * Get stats of the open tubes.
     * Each entry (keyed by tube) contains the following keys, pointing to the number of
     * corresponding jobs on the table:
     *   - buried (failed);
     *   - delayed (going to be ready for work only later);
     *   - ready (waiting for being worked on);
     *   - reserved (being worked on);
     *   - total (sum of all).
     * @param string $filterTube Return only data regarding a given tube
     * @return array[]
     * @todo turn this into an ArrayObject as well, so we get property hints
     */
    public function stats($filterTube = null)
    {
        $query = JobModel::query()
            ->columns([
                'tube',
                'COUNT(*) AS total',
                Job::ST_BURIED,
                Job::ST_RESERVED,
                'priority < :mid_priority: AS '.Job::ST_URGENT,
                'delay >= :now: AS '.Job::ST_DELAYED,
            ])
            ->bind([
                'mid_priority' => Job::PRIORITY_MEDIUM,
                'now'          => time(),
            ])
            ->groupBy(['tube', Job::ST_BURIED, Job::ST_RESERVED, Job::ST_URGENT, Job::ST_DELAYED])
            ->orderBy('tube');

        if ($filterTube) {
            $query->where('tube = :tube:', ['tube' => $filterTube]);
        }

        $result = $query->execute();

        $structure = function ($name) {
            return [
                Job::ST_BURIED   => 0,
                Job::ST_DELAYED  => 0,
                'name'           => $name,
                Job::ST_READY    => 0,
                Job::ST_RESERVED => 0,
                'total'          => 0,
                Job::ST_URGENT   => 0,
            ];
        };

        if ($filterTube) {
            $stats = ($filterTube == $this->using) ? [$this->using => $structure($this->using)] : [];
        } else {
            $stats = [
                'all'        => $structure('all'),
                $this->using => $structure($this->using),
            ];
        }
        foreach ($result->toArray() as $entry) {
            $tube  = $entry['tube'];
            $total = $entry['total'];
            unset($entry['total'], $entry['tube']);

            if (!array_key_exists($tube, $stats)) {
                $stats[$tube] = $structure($tube);
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
     *   - buried (failed);
     *   - delayed (going to be ready for work only later);
     *   - ready (waiting for being worked on);
     *   - reserved (being worked on);
     *   - total (sum of all).
     * @param string $tube
     * @return array
     */
    public function statsTube($tube = null)
    {
        return $this->stats($tube ?: $this->using);
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
     * @return false|\Phalcon\Queue\Db\Job
     */
    public function peek($id)
    {
        if ($job = JobModel::findFirst($id)) {
            return new Job($this, $job);
        } else {
            return false;
        }

    }

    /**
     * Finds a job given certain conditions.
     * @param array|string $conditions One or more SQL conditions. Each array entry will be joined with "AND".
     * @param array        $bind       Bind params, if needed.
     * @return null|Job
     */
    protected function queriedPeek($conditions, array $bind = [])
    {
        $conditions = array_merge((array) $conditions, ['tube IN ({tubes:array})']);

        $job = JobModel::findFirst([
            'conditions' => implode(' AND ', $conditions),
            'bind'       => array_merge(['tubes' => $this->watching], $bind),
            'order'      => 'priority ASC, id ASC',
        ]);

        return $job ? new Job($this, $job) : false;
    }

    /**
     * Inspect the next ready job.
     * @return null|\Phalcon\Queue\Db\Job
     */
    public function peekReady()
    {
        return $this->queriedPeek(['delay <= :now:', 'reserved = 0', 'buried = 0'], ['now' => time()]);
    }

    /**
     * Return the next job in the list of buried jobs.
     * @return bool|\Phalcon\Queue\Db\Job
     */
    public function peekBuried()
    {
        return $this->queriedPeek('buried = 1');
    }

    /**
     * Inspect the next delayed job.
     * @return null|\Phalcon\Queue\Db\Job
     */
    public function peekDelayed()
    {
        return $this->queriedPeek(['delay > :now:', 'reserved = 0'], ['now' => time()]);
    }

    /**
     * Kicks back into the queue $number buried jobs (at least one).
     * @param int $number
     * @return bool|int total of actually kicked jobs, or false on failure
     */
    public function kick($number = 1)
    {
        //from the Beanstalk tutorial: "Buried jobs are maintained in a special FIFO-queue outside of
        //  the normal job processing lifecycle until they are kicked alive again"
        //  so, there's no need to filter by tubes or use special order
        //TODO: actually, to make it perfect we would need to store the timestamp the job was buried and order by it
        $entries = JobModel::query()
            ->where('buried = 1')
            ->orderBy('id DESC')
            ->limit($number)
            ->execute();
        $total   = sizeof($entries);
        $updated = $entries->update(['buried' => 0]);

        return $updated ? $total : false;
    }

    public function __sleep()
    {
        return ['diServiceKey', 'using', 'watching'];
    }

    public function __wakeup()
    {
        $this->connect();
    }

    public function read($length = 0)
    {
        throw new BadMethod('"read" is not a valid method in DB queues.');
    }

    public function write($data)
    {
        throw new BadMethod('"write" is not a valid method in DB queues.');
    }

    public function disconnect()
    {
        throw new BadMethod('"disconnect" is not a valid method in DB queues.');
    }
}
