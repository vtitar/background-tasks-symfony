# 1. Bundle overview

Symfony BackgroundTasksBundle. Create and process tasks that will be executed via cron. Could be configured time when 
task should be executed, separate process for specific task groups.

# 2. Installation

### Install bundle

`composer require vtitar/background-tasks-symfony`

### Create db after installation

`./bin/console doctrine:schema:update`

# 3. Configuration

 - Add new cron config to crontab - will execute all tasks without specific group
 
`* * * * *   /usr/bin/flock -n /home/project/path/var/locks/cron-background-tasks.lock /home/project/path/bin/console tit:background-task:run > /home/project/path/var/log/cron-background-tasks.log 2>&1`

 - In case you need to have separate process for some tasks to not block/wait main process - configure separate cron for tasks with specific group only

`* * * * *   /usr/bin/flock -n /home/project/path/var/locks/cron-background-tasks-group-test.lock /home/project/path/bin/console tit:background-task:run --group_code=test-group > /home/project/path/var/log/cron-background-tasks-group-test.log 2>&1`

# 4. How to use

## 4.1 Add new entity to execution list

### 4.1.1 Add via entityManager

New entity could be added via entityManager like
```php
$task = new BackgroundTask();
$task->setCreatedAt(new DateTimeImmutable());
...
$this->entityManager->persist($task);
$this->entityManager->flush();
```

### 4.1.2 Add via BackgroundTaskManager

Or BackgroundTaskManager could be used. Add `tit.background_tasks.manager.background_task` service
to your service where you want to use it. Add to controller like 
```php
public function __construct(
    private readonly BackgroundTaskManager      $backgroundTaskManager
){}
```

And add new task
```php
$params = [
    'request_id' => 123
];

$this->backgroundTaskManager->addNewTask(
    'tit.test.test', # service that will process task
    'handle', # function that will process task
    $params,
    '',
    null,
    50
);

$this->backgroundTaskManager->saveToDb();
```

## 4.2 Add service and function that should process task

### 4.2.1 Service should be public

```xml
<service id="tit.test.test" class="Tit\Bundle\Service\Test" public="true" >
    <argument type="service" id="Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface" />
</service>
```

### 4.2.2 Add function that will process task

```php
public function handle(array $params): void
{
    $requestId = $params['request_id'];
    // do whatever you need
}
```

## 4.3 Errors checking

You could easily check is task executed okay via db `background_task.status` column.
 - error - status should be -1. Error should be saved in `background_task.last_error` column.
 - if you want to add task back to queue - set `status` to 0