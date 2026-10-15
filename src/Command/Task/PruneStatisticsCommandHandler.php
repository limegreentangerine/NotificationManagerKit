<?php

namespace NotificationManagerKit\Command\Task;

use NotificationManagerKit\Entity\Settings;
use NotificationManagerKit\Support\StatsStore;
use Concrete\Core\Command\Task\Output\{OutputAwareInterface, OutputAwareTrait};

class PruneStatisticsCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    public function __invoke(PruneStatisticsCommand $command)
    {
        $days = Settings::get()->getStatsRetentionDays();
        $removed = StatsStore::make()->prune($days);

        $this->output->write(t('Removed %1$s statistics rows older than %2$s days.', $removed, $days));
    }
}
