<?php namespace Phalcon\Queue\Db;

use Phalcon\Queue\Db;
use Phalcon\Queue\Db\Job\Stats;
use Phalcon\Queue\Db\Model as JobModel;

/**
 * Job from the DB backend.
 */
class Job extends \Phalcon\Queue\Beanstalk\Job
{
    /**
     * @var JobModel
     * @see getModel()
     */
    protected $model;

    /**
     * Used while the instance is still valid but the job was deleted.
     * @var bool
     */
    protected $deleted = false;

    const PRIORITY_HIGHEST = 0;
    const PRIORITY_MEDIUM  = 2147483648; // 2^31
    const PRIORITY_LOWEST  = 4294967295; // 2^32 -1
    const PRIORITY_DEFAULT = self::PRIORITY_MEDIUM;

    const ST_BURIED   = 'buried';
    const ST_READY    = 'ready';
    const ST_DELAYED  = 'delayed';
    const ST_RESERVED = 'reserved';
    const ST_URGENT   = 'urgent';
    const ST_DELETED  = 'deleted';

    public function __construct(Db $queue, JobModel $model)
    {
        parent::__construct($queue, $model->id, $model->body);
        $this->setModel($model);
    }

    protected function setModel(JobModel $model)
    {
        $this->model = $model;
    }

    /**
     * @return JobModel
     */
    public function getModel()
    {
        if ($this->model instanceof JobModel) {
            return $this->model;
        } else {
            return $this->model = JobModel::findFirst($this->getId());
        }
    }

    public function getId()
    {
        return (int) $this->_id;
    }

    public function getState()
    {
        if ($this->deleted) {
            return self::ST_DELETED;
        } elseif ($this->model->reserved) {
            return self::ST_RESERVED;
        } elseif ($this->model->buried) {
            return self::ST_BURIED;
        } elseif ($this->model->delay > time()) {
            return self::ST_DELAYED;
        } elseif ($this->model->priority < self::PRIORITY_MEDIUM) {
            return self::ST_URGENT;
        } else {
            return self::ST_READY;
        }
    }

    public function getBody()
    {
        return unserialize($this->model->body);
    }

    public function delete()
    {
        //FIXME: good boy, Phalcon\Beanstalk. You don't even try to implement the actual Beanstalk spec!!!
        //skipping this one to maintain compatibility with the original implementation
//        if (!in_array($this->getState(), [self::ST_BURIED, self::ST_RESERVED])) {
//            throw new InvalidJobOperationException('Only buried or reserved jobs can be deleted');
//        }
        $this->deleted = true;
        return $this->model->delete();
    }

    public function release($priority = null, $delay = 0)
    {
        $data = [
            'reserved' => 0,
            'delay'    => ($delay > 0) ? time() + $delay : 0,
        ];
        if ($priority) {
            $data['priority'] = $priority;
        }

        return $this->model->update($data);
    }

    public function bury($priority = null)
    {
        $data = [
            'buried'   => 1,
            'reserved' => 0,
        ];
        if ($priority) {
            $data['priority'] = $priority;
        }

        return $this->model->update($data);
    }

    //TODO: implement
//    public function touch()
//    {
//        parent::touch();
//    }

    public function kick()
    {
        $payload = [];
        switch ($this->getState()) {
            case self::ST_BURIED: $payload  = ['buried' => 0]; break;
            case self::ST_DELAYED: $payload = ['delay' => 0]; break; //FIXME: missing tests for kicking a delayed job!
        }

        return $payload ? $this->model->update($payload) : true;
    }

    /**
     * @return Stats
     */
    public function stats()
    {
        if ($this->deleted) {
            throw new InvalidJobOperationException('Cannot get stats from deleted job');
        }
        $model = $this->model;
        $delay = $model->delay - time();
        return new Stats([
            'id'            => $this->getId(),
            'age'           => time() - $model->created_at,
            'state'         => $this->getState(),
            'tube'          => $model->tube,
            'delay'         => ($delay > 0) ? $delay : 0,
            'delayed_until' => (int) $model->delay,
            'priority'      => (int) $model->priority,
            'priority_text' => $model->priorityText(),
        ]);
    }

    public function __sleep()
    {
        return ['deleted', '_id', '_body', '_queue'];
    }

    public function __wakeup()
    {
        parent::__wakeup();
        $this->getModel(); //caches model information from job id
    }
}
