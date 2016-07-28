<?php namespace Phalcon\Queue\Db;

/**
 * @todo why do we need to set default values here? Aren't the database defaults enough for Phalcon?
 */
class Model extends \Phalcon\Mvc\Model
{
    public $id;

    public $tube = 'default';

    public $body;

    public $created_at;

//    public $ttr = 0;

    public $delay = 0;

    public $priority = 2147483648;

    public $reserved = 0;

    public $buried = 0;

    public function getSource()
    {
        return 'jobs';
    }

    public function initialize()
    {
        //FIXME: it seems the release procedure fails with this enabled x_x
//        $this->useDynamicUpdate(true);
    }

    public function beforeValidationOnCreate()
    {
        $this->created_at = time();
    }

    public function priorityText()
    {
        switch ($this->priority) {
            case Job::PRIORITY_HIGHEST: return 'highest';
            case Job::PRIORITY_MEDIUM: return 'medium';
            case Job::PRIORITY_LOWEST: return 'lowest';
            default: return ($this->priority < Job::PRIORITY_MEDIUM) ? 'high' : 'low';
        }
    }
}
