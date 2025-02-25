<?php

namespace Tit\BackgroundTasksBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tit\BackgroundTasksBundle\Service\Manager\BackgroundTaskManager;


#[AsCommand(
    name: 'tit:background-task:run',
    description: 'Run Background Tasks'
)]
class RunBackgroundTaskCommand extends Command
{

    /**
     * @param ContainerInterface $container
     * @param BackgroundTaskManager $backgroundTaskManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ContainerInterface         $container,
        private readonly BackgroundTaskManager      $backgroundTaskManager,
        private readonly LoggerInterface            $logger
    )
    {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'status',
                null,
                InputOption::VALUE_OPTIONAL,
                'Run tasks with this status',
                0
            )
            ->addOption(
                'bunch_size',
                null,
                InputOption::VALUE_OPTIONAL,
                'Page size to be processed',
                50
            )
            ->addOption(
                'group_code',
                null,
                InputOption::VALUE_OPTIONAL,
                'Group to be processed, empty - process tasks without groups only',
                ''
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $datetime = new \DateTime();
        $formatedDateTime = $datetime->format('Y-m-d H:i:s');
        $io->info("Started: $formatedDateTime");

        $statusToProcess = $input->getOption('status');
        $bunchSize = $input->getOption('bunch_size');
        $groupCode = $input->getOption('group_code');

        $this->processBackgroundTasks($statusToProcess, $bunchSize, $groupCode);

        $datetimeEnd = new \DateTime();
        $formatedEndTime = $datetimeEnd->format('Y-m-d H:i:s');
        $io->info("Finished: $formatedEndTime");

        return Command::SUCCESS;
    }

    /**
     * @param int $statusToProcess
     * @param int $bunchSize
     * @param string $groupCode
     * @return void
     */
    protected function processBackgroundTasks(int $statusToProcess, int $bunchSize, string $groupCode): void
    {
        $start = time();
        $i = 0;

        do {
            $pendingTasks = $this->backgroundTaskManager->findTasks($statusToProcess, $groupCode, $bunchSize);
            foreach ($pendingTasks as $pendingTask) {
                try {
                    //started execution - change status to processing
                    $pendingTask->setStatus(BackgroundTaskManager::STATUS_PROCESSING);
                    $pendingTask->setStartedAt(new \DateTimeImmutable());
                    $this->backgroundTaskManager->persist($pendingTask);
                    $this->backgroundTaskManager->saveToDb();

                    //execute
                    $serviceAlias = $pendingTask->getService();
                    $method = $pendingTask->getMethod();
                    $params = $pendingTask->getParams();

                    $service = $this->container->get($serviceAlias);
                    $service->$method($params);

                    //executed - change status to Completed
                    $pendingTask->setStatus(BackgroundTaskManager::STATUS_COMPLETE);
                    $pendingTask->setFinishedAt(new \DateTimeImmutable());
                    $this->backgroundTaskManager->persist($pendingTask);
                    $this->backgroundTaskManager->saveToDb();
                } catch (\Exception | \Throwable $exception) {
                    $pendingTask->setStatus(BackgroundTaskManager::STATUS_FAILED);
                    $pendingTask->setFinishedAt(new \DateTimeImmutable());
                    $pendingTask->setLastError($exception->getMessage());

                    $this->backgroundTaskManager->persist($pendingTask);
                    $this->backgroundTaskManager->saveToDb();

                    $this->logger->error('Task is Failed: ' . $pendingTask->getId(), [
                        'exception' => $exception->getMessage(),
                        $exception
                    ]);
                }
            }

            $i += count($pendingTasks);
            echo $i . " loaded in " . (time() - $start) . "sec | peak memory " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";

            $this->backgroundTaskManager->saveToDb(true);

        } while(count($pendingTasks) === $bunchSize);

        //remove old completed tasks
        try {
            $this->backgroundTaskManager->removeOldTasks(BackgroundTaskManager::STATUS_COMPLETE, 3);
        } catch (\Exception | \Throwable $exception) {
            $this->logger->error('Old tasks were not removed', [
                'exception' => $exception->getMessage(),
                $exception
            ]);
        }
    }
}