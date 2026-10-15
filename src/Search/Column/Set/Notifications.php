<?php

namespace NotificationManagerKit\Search\Column\Set;

use Concrete\Core\Search\Column\{Column, Set};

class Notifications extends Set
{
    public function __construct()
    {
        $this->addColumn(new Column('', t('Name'), 'getAnyName', false));
        $this->addColumn(new Column('n.type', t('Type'), 'getTypeString', true));
        $this->addColumn(new Column('n.active', t('Status'), 'getStatusString', true));
        $this->addColumn(new Column('', t('Shown On'), 'getDisplayScopeString', false));
        $this->addColumn(new Column('n.priority', t('Priority'), 'getPriority', true));
        $this->addColumn(new Column('n.date_updated', t('Date Updated'), 'getDateUpdatedString', true));

        $this->setDefaultSortColumn($this->getColumnByKey('n.date_updated'), 'desc');
    }
}
