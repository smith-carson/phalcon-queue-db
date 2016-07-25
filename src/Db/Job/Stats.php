<?php namespace Phalcon\Queue\Db\Job;

use Phalcon\Text;

/**
 * Helper class for Job stats, accessible both through array brackets or object inference.
 *
 * @property int    $id           Job id
 * @property int    $age          How many seconds since this job was created
 * @property string $state        Human representation of a job current status. See Job::ST_*
 * @property string $tube         Current tube this job was put on
 * @property int    $delay        How many seconds until this job gets ready to be run
 * @property int    $delayedUntil Epoch timestamp of when the job will be available
 * @property int    $priority     Priority number, being 0 highest and 2^32 lowest. See Job::PRIORITY_*
 * @property string $priorityText Textual representation of job priority. See {@link Model::priorityText()}
 */
class Stats extends \ArrayObject
{
    public function __construct(array $stats, $flags = 0, $iteratorClass = \ArrayIterator::class)
    {
        $finalInput = [];
        foreach ($stats as $key => $value) {
            $finalInput[$this->correctCase($key)] = $value;
        }

        parent::__construct($finalInput, $flags, $iteratorClass);
    }

    public function __get($key)
    {
        return $this->offsetGet($key);
    }

    public function offsetExists($offset)
    {
        return parent::offsetExists($this->correctCase($offset));
    }

    public function offsetGet($offset)
    {
        $offset = $this->correctCase($offset);
        return $this->offsetExists($offset) ? parent::offsetGet($offset) : null;
    }

    private function correctCase($key)
    {
        if (strpos($key, '_')) {
            return lcfirst(Text::camelize($key));
        } else {
            return $key;
        }
    }

    private static function ro()
    {
        throw new \OverflowException('Queue\Db\Job\Stats is a read-only class');
    }

    public function append($offset)
    {
        self::ro();
    }

    public function exchangeArray($input)
    {
        self::ro();
    }

    public function offsetSet($offset, $value)
    {
        self::ro();
    }

    public function offsetUnset($offset)
    {
        self::ro();
    }
}
