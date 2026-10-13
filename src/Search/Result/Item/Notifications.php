<?php

namespace NotificationManagerKit\Search\Result\Item;

use Concrete\Core\Search\Result\Item;
use ClassKit\Search\Result\Item\ItemTrait;
use Concrete\Core\Support\Facade\Url as URL;

class Notifications extends Item
{
    use ItemTrait;

    public function getViewUrl()
    {
        return URL::to('/dashboard/notification_manager/notifications/details', $this->entity->getID());
    }
}
