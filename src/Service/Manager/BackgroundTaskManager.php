<?php

namespace Tit\BackgroundTasksBundle\Service\Manager;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Tit\BackgroundTasksBundle\Entity\BackgroundTask;

readonly class BackgroundTaskManager
{
    const STATUS_IN_QUEUE = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_COMPLETE = 2;
    const STATUS_FAILED = -1;

    const BUNCH_LIMIT = 50;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * @param string $service
     * @param string $method
     * @param array $params
     * @param string $groupCode
     * @param DateTimeImmutable|null $runAfter
     * @param int $priority
     * @return BackgroundTask
     */
    public function addNewTask(
        string $service,
        string $method,
        array $params = [],
        string $groupCode = '',
        DateTimeImmutable $runAfter = null,
        int $priority = 50
    ): BackgroundTask {
        $task = new BackgroundTask();
        $task->setCreatedAt(new DateTimeImmutable());
        $task->setService($service);
        $task->setMethod($method);
        $task->setParams($params);
        $task->setStatus(self::STATUS_IN_QUEUE);
        $task->setGroupCode($groupCode);
        $task->setRunAfter($runAfter);
        $task->setPriority($priority);

        $this->entityManager->persist($task);

        return $task;
    }

    /**
     * @param int $status
     * @param string $groupCode
     * @param int $limit
     * @return array
     */
    public function findTasks(
        int $status = self::STATUS_IN_QUEUE,
        string $groupCode = '',
        int $limit = self::BUNCH_LIMIT,
    ): array {
        $now = new DateTimeImmutable('now');

        $qb = $this->entityManager->getRepository(BackgroundTask::class)
            ->createQueryBuilder('bt')
            ->where('bt.status = :status')
            ->andWhere('(bt.run_after IS NULL OR bt.run_after <= :now)')
            ->andWhere('(bt.group_code = :group_code)');

        $qb->orderBy('bt.priority', 'desc')
            ->setMaxResults($limit);

        $qb->setParameter('status', $status);
        $qb->setParameter('group_code', $groupCode);
        $qb->setParameter('now', $now->format("Y-m-d H:i:s"));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int $status
     * @param int $days
     * @return void
     * @throws \Exception
     */
    public function removeOldTasks(int $status, int $days): void
    {
        $dateTimeMinutesAgo = new \DateTime($days . " days ago");
        $since = $dateTimeMinutesAgo->format("Y-m-d H:i:s");

        $query =  $this->entityManager->getRepository(BackgroundTask::class)->createQueryBuilder('task')
            ->andwhere('task.status = :status')
            ->andwhere('task.finished_at < :since');

        $query->setParameter('since', $since);
        $query->setParameter('status', $status);

        $oldTasks = $query->getQuery()->getResult();

        foreach ($oldTasks as $oldTask) {
            $this->entityManager->remove($oldTask);
        }

        $this->entityManager->flush();
    }

    /**
     * @param bool $shouldClear
     * @return void
     */
    public function saveToDb(bool $shouldClear = false): void
    {
        $this->entityManager->flush();

        if ($shouldClear) {
            $this->entityManager->clear();
        }
    }

    /**
     * @param object $obj
     * @return void
     */
    public function persist(object $obj): void
    {
        if (method_exists($obj, 'setUpdatedAt')) {
            $obj->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($obj);
    }

}
