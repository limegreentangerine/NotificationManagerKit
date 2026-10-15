<?php

namespace Concrete\Package\NotificationManagerKit\Controller\SinglePage\Dashboard;

use Concrete\Core\Page\Controller\DashboardPageController;

class NotificationManager extends DashboardPageController
{
    public function view()
    {
        $this->buildRedirect('/dashboard/notification_manager/notifications')->send();
    }
}
