Phalcon package for Queue through the Database
==============================================

[![StyleCI](https://styleci.io/repos/62728787/shield?style=flat-square)](https://styleci.io/repos/62728787)
[![Build Status](https://img.shields.io/travis/igorsantos07/phalcon-queue-db/master.svg?style=flat-square)](https://travis-ci.org/igorsantos07/phalcon-queue-db)
[![Latest Version](https://img.shields.io/packagist/v/igorsantos07/phalcon-queue-db.svg?style=flat-square)](https://github.com/igorsantos07/phalcon-queue-db/releases)
[![Software License](https://img.shields.io/packagist/l/igorsantos07/phalcon-queue-db.svg?style=flat-square)](docs/LICENSE.md)

This package sits side by side with [\Phalcon\Queue\Beanstalk`][original] as a
way to provide job queuing for those who don't want to bother with installing
and maintaining a Beanstalk server.

This is mostly useful when you have a low throughput of jobs. It is advised to
use something faster such as Beanstalk if you plan to have a lot of jobs and
workers happening at the same time - as that may put a lot of strain in your
database and disk I/O, slowing jobs down.

Installation
------------

1. drop this package in your composer installation:
`composer require igorsantos07/phalcon-queue-db`
2. create the needed table by importing one of the files from `sql/`. You may
   copy it into a migration or whatever it's needed in your setup to get a new
   table up and running :)
3. Read the rest of this doc to know how to use it and profit!

Usage
-----

As most job queuing systems, the idea here is to take off some load of a part
of the application and run that outside. Thus, there are two parts that interact
together: when you [queue a job][#job-queuing], and when you [work on it][#job-processing].

> As this package is based upon the original Beanstalk implementation by
Phalcon, you may also find useful to read both the [base-class documentation][original]
and an [almost complete tutorial on Beanstalk][beanstalk-tutorial]. It is good
to warn, though, that there are other features implemented that are not covered
by the original class, but we didn't follow some strict behaviours of Beanstalk
as the base class didn't follow as well - we're striving for additional features
without the cost of backwards compatibility.

> For the following samples, consider the following `uses`:
>
>     use \Phalcon\Queue\Db as DbQueue;
>     use \Phalcon\Queue\Db\Job as Job;
>

### Job queuing

#### 1. The actual queue
To get the queue object, simply instantiate it. The sole argument is your
database connection name, as found in Phalcon DI - the default is `db`.
You may want to set the queue itself in your Dependency Injection container
as well.

    $queue = new DbQueue(); //gets a database connection named db from the DI
    $outsiderQueue = new DbQueue('weird_db'); another queue, in another db?

#### 2. Adding stuff to the queue
So, there's something you want to do later. Let's say you need to send a bunch
of emails all at once, and that would take a while if happened during the user
request. We have the concept of "job tubes", as in separate tubes get different
types of jobs, allowing you to have specialized workers for each type of job.

If no tube is specified, the default one is called... you guessed it, "default".

    class ImportantController {
        function veryImportantAction()
        {
            //do some stuff and ends up with an email list
            //instead of sending all those emails from the user request,
            //we are going to hand this job to a worker
            $queue = new DbQueue();
            $queue->choose('email_notification'); //sets the tube we'll be using
            $queue->put($emailList);

            //tell the user to be happy because stuff went ok
        }
    }

Some useful pieces of code in this phase are:

- `DbQueue::choose($tube)` - defines what tube to put stuff on
- `DbQueue::using()` - tells you what tube is being used to put stuff on
- `DbQueue::put($body, $options)` - stores a job in the queue, and returns its ID

> One difference of the original Beanstalk implementation is: there's no need
for the job body to be a string. As long as you give the job something
serializable, you're good to go.

#### 3. Job options
It's also possible to define some specific options for jobs.

> We'll see how those interact in the next section, about retrieving jobs and
working on them.

    //adds a job on top of the queue
    $queue->put($bossEmails, [DbQueue::OPT_PRIORITY => Job::PRIORITY_HIGHEST]);
    //adds a job to be ran only later (in seconds)
    $queue->put($taskReminder, [DbQueue::OPT_DELAY => 60 * 10]);

There are a couple of constants in the `Job` class that define other priority
presets. If no priority is given, the default one is `Job::PRIORITY_MEDIUM`.


### Job processing
From your command-line script (herein called _worker_), you can process jobs by
using peek or reserve methods on the queue. Peek jobs are advised only for
verifications and maintenance: actual work should be made on reserved jobs only.

    $queue->watch('email_notification');
    while ($job = $queue->reserve()) {
        $payload = $job->getBody();
        //do stuff with the payload
        if ($worked) {
            $job->delete();
        } else {
            $job->bury();
        }
    }

Useful bits here:

- `DbQueue::watch($tubes, $replace)` - defines which tubes to get jobs from.
  Each watched tube gets into a stack, and the first job found in any watched
  tube will be retrieved on `reserve()` or [`peek*()` calls][#peeking-into-the-queue].
  The default tube to watch is, you guessed it, "default".
- `DbQueue::ignore($tube)` - removes a tube from the watch list. Keep in mind
  that the watch list will never be empty: if you try to ignore the last one,
  actually _your call will be ignored_.
- `DbQueue::reserve($timeout)` - returns a job as soon as there's one available.
  `$timeout` makes the method return false after waiting that amount of seconds.
  Another `reserve()` call won't retrieve a reserved job, only another one.
  Thus, this method is pool-safe - you can have a lot of workers running at once
  and no two workers will receive the same job.
- `Job::getBody()` - retrieves the job payload that was originally given on
  `DbQueue::put()`.
- `Job::delete()` - when finished working on a given job, delete it!
- `Job::bury($priority)` - stores the job back with a special "buried" status,
  meaning it failed to finish.
- `Job::release()` - gives the job back to the queue without marking it as a
  failure.

#### Peeking into the queue
For maintenance tasks you can use various peeking methods to see the current
queue status:

- `DbQueue::peek($id)` returns the job referred by a specific ID
- `DbQueue::peekReady()` gets the next ready job in the queue, or false
- `DbQueue::peekBuried()` gets the first buried job from the queue, or false
- `DbQueue::peekDelayed()` gets the first delayed job from the queue, or false

> Remember that jobs are always ordered by priority and age: urgent jobs come
always first, and then older jobs are returned in front of newer ones.

#### Kicking jobs back
To put a buried job back in the normal queue for processing, or to advance a
delayed job in the line, you can use `Job::kick()`. If you want to move several
buried jobs back in line at once, there's also `DbQueue::kick($numberOfJobs)`.

> Tip: you may want to create a separate database connection in the DI that is
_persistent_. This way there will be no need to restart the connection every
time the worker is called.

#### Maintenance
Last but not least, there's a number of helper methods to get you additional
information on the your current queue state:

- `Job::stats()` will give you the job ID, age, tube, state, as well as delay
  and priority details. There are a couple of class constants to match some of
  those, such as `Job::PRIORITY_*` or `Job::ST_*`. _Note: you can't get stats
  from a deleted job, ok?_
- `Job::getId()` and `Job::getState()` will give you the job... ID and state -
  the latter matching one of the `Job::ST_*` constants.
- `DbQueue::stats()` will retrieve statistics about all tubes, while
  `statsTube($tube)` will give you info on only one tube - by default, the one
  being used by default. Those stats will include total of jobs, as well as
  count of buried, delayed, urgent and ready jobs - and the tube name.
- `DbQueue::watching()` and `DbQueue::using()/chosen()` - these will answer you
  which tubes jobs will be taken from, and where they'll be put in.
- `DbQueue::listTubes()` will tell you all currently active tubes in the queue.
  Tubes that were used before but has no job in line currently will not be
  displayed.


### Bonus: the Job Model
As this is a database library, we do use models to interact with the actual
database table. That said, you'll hardly ever need that.

Well, if you do need, you can retrieve the model related to a job by using
`Job::getModel()`, or by using the `Db\Model` class directly.

[original]: https://docs.phalconphp.com/en/latest/reference/queue.html
[beanstalk-tutorial]: https://github.com/earl/beanstalkc/blob/master/TUTORIAL.mkd
