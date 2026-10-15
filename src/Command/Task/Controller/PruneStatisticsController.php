<?php

namespace NotificationManagerKit\Command\Task\Controller;

use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Controller\AbstractController;
use NotificationManagerKit\Command\Task\PruneStatisticsCommand;
use Concrete\Core\Command\Task\Runner\{ProcessTaskRunner, TaskRunnerInterface};

class PruneStatisticsController extends AbstractController
{
    public function getName(): string
    {
        return t('Prune Notification Statistics');
    }

    public function getDescription(): string
    {
        return t('Deletes notification impression, click and dismissal counts older than the retention period set in Notification Manager settings.');
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        return new ProcessTaskRunner(
            $task,
            new PruneStatisticsCommand(),
            $input,
            t('Pruning notification statistics...'),
        );
    }
}
