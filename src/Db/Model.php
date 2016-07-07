<?php namespace Phalcon\Queue\Db;

/**
 * @todo why do we need to set default values here? Aren't the database defaults enough for Phalcon?
 */
class Model extends \Phalcon\Mvc\Model
{
    public $id;

    public $tube = 'default';

    public $body;

    public $ttr = 0;

    public $delay = 0;

    public $priority = 0;

    public function getSource() { return 'jobs'; }

}