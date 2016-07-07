<?php namespace Phalcon\Queue;
use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Di\Injectable;
use Phalcon\Queue\Db\Model as JobModel;

require_once __DIR__.'/../tests/unit/DbTest.php';

/**
 * Tries to mimic Phalcon's Beanstalk Queue class for low-throughput queue needs
 *
 * <code>
 * $queue = new \Phalcon\Queue\Db();
 * </code>
 */
class Db extends Injectable
{

    /** @var \Phalcon\Db\Adapter\Pdo */
    protected $connection;

    /**
     * Queue manager constructor. By default, will look for a service called 'db'.
     * @todo implement some way to force a persistent db connection
     * @param string $di_service_key
     */
    public function __construct($di_service_key = 'db')
    {
        $this->connection = $this->getDI()->get($di_service_key);
    }

    /**
     * Makes a connection to the Beanstalkd server
     *
     * @return resource
     */
    public function connect() { }

    /**
     * Inserts jobs into the queue
     *
     * @param mixed $data
     * @param array $options
     * @return string|bool
     */
    public function put($data, $options = []) {
        $payload = array_merge($options, ['body' => serialize($data)]);
        $job = new JobModel();
        $job->save($payload);
        return $job->id;
    }

    /**
     * Reserves a job in the queue
     *
     * @param mixed $timeout
     * @return bool|\Phalcon\Queue\Beanstalk\Job
     */
    public function reserve($timeout = null) { }

    /**
     * Change the active tube. By default the tube is "default"
     *
     * @param string $tube
     * @return bool|string
     */
    public function choose($tube) { }

    /**
     * Change the active tube. By default the tube is "default"
     *
     * @param string $tube
     * @return bool|string
     */
    public function watch($tube) { }

    /**
     * Get stats of the Beanstalk server.
     *
     * @return bool|array
     */
    public function stats() { }

    /**
     * Get stats of a tube.
     *
     * @param string $tube
     * @return bool|array
     */
    public function statsTube($tube) { }

    /**
     * Get list of a tubes.
     *
     * @return bool|array
     */
    public function listTubes() {
        $result = JobModel::query()
            ->distinct(true)
            ->columns('tube')
            ->orderBy('tube')
            ->execute()
            ->toArray();
        return array_column($result, 'tube');
    }

    /**
     * Inspect the next ready job.
     *
     * @return bool|\Phalcon\Queue\Beanstalk\Job
     */
    public function peekReady() { }

    /**
     * Return the next job in the list of buried jobs
     *
     * @return bool|\Phalcon\Queue\Beanstalk\Job
     */
    public function peekBuried() { }

    /**
     * Reads the latest status from the Beanstalkd server
     *
     * @return array
     */
    final public function readStatus() { }

    /**
     * Fetch a YAML payload from the Beanstalkd server
     *
     * @return array
     */
//    final public function readYaml() {}

    /**
     * Reads a packet from the socket. Prior to reading from the socket will
     * check for availability of the connection.
     *
     * @param int $length
     * @return bool|string
     */
    public function read($length = 0) { }

    /**
     * Writes data to the socket. Performs a connection if none is available
     *
     * @param string $data
     * @return bool|int
     */
    protected function write($data) { }

    /**
     * Closes the connection to the beanstalk server.
     *
     * @return bool
     */
    public function disconnect() { }

}