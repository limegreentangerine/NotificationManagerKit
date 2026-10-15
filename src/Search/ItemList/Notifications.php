<?php

namespace NotificationManagerKit\Search\ItemList;

use ClassKit\Search\ItemList\ListTrait;
use Doctrine\ORM\EntityManagerInterface;
use Concrete\Core\Search\Pagination\Pagination;
use Concrete\Core\Search\ItemList\Database\ItemList;
use NotificationManagerKit\Entity\AbstractNotification;
use Concrete\Core\Application\{ApplicationAwareInterface, ApplicationAwareTrait};

class Notifications extends ItemList implements ApplicationAwareInterface
{
    use ApplicationAwareTrait;
    use ListTrait;

    protected $autoSortColumns = [
        'n.type',
        'n.priority',
        'n.active',
        'n.date_updated',
    ];

    protected function createPaginationObject()
    {
        $query = $this->deliverQueryObject();
        $prefetch = $this->prefetch(...);
        $adapter = new class($query, function (\Doctrine\DBAL\Query\QueryBuilder $query) {
            $query
                ->resetQueryParts(['groupBy', 'orderBy'])
                ->select('count(distinct n.id)')
                ->setMaxResults(1);
        }, $prefetch) extends \Pagerfanta\Doctrine\DBAL\QueryAdapter {
            public function __construct($queryBuilder, $countQueryBuilderModifier, private \Closure $prefetch)
            {
                parent::__construct($queryBuilder, $countQueryBuilderModifier);
            }

            public function getSlice($offset, $length)
            {
                $rows = parent::getSlice($offset, $length);
                ($this->prefetch)($rows);

                return $rows;
            }
        };

        return new Pagination($this, $adapter);
    }

    /**
     * Load these rows' notifications with their contents in one query, so getResult() finds them in the
     * entity manager instead of querying once per row.
     */
    protected function prefetch(iterable $rows): void
    {
        $ids = array_column(is_array($rows) ? $rows : iterator_to_array($rows), 'id');

        if ($ids === []) {
            return;
        }

        app(EntityManagerInterface::class)
            ->createQuery('SELECT n, d FROM ' . AbstractNotification::class . ' n LEFT JOIN n.data d WHERE n.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getResult();
    }

    public function executeGetResults()
    {
        $rows = parent::executeGetResults();
        $this->prefetch($rows);

        return $rows;
    }

    public function createQuery()
    {
        $this->query->select('n.id')->from('nmNotifications', 'n');
    }

    /**
     * Name lives on the per-locale content rows, so match any locale's name.
     */
    public function filterByName($name)
    {
        $name = (string) $name;

        if ($name === '') {
            return;
        }

        $this->query->andWhere(
            'n.id IN (SELECT nc.notificationId FROM nmNotification_Contents nc '
            . 'INNER JOIN nmNotificationContents c ON c.id = nc.notificationContentId '
            . 'WHERE c.name LIKE ' . $this->query->createNamedParameter('%' . addcslashes($name, '%_\\') . '%') . ')',
        );
    }

    public function filterByType($type)
    {
        if (in_array($type, [AbstractNotification::TYPE_BANNER, AbstractNotification::TYPE_POPUP], true)) {
            $this->filterBy('n.type', $type);
        }
    }

    public function getTotalResults()
    {
        $query = $this->deliverQueryObject();
        $query
            ->resetQueryParts(['groupBy', 'orderBy'])
            ->select('count(distinct n.id)')
            ->setMaxResults(1);

        return (int) $query->execute()->fetchOne();
    }

    public function getResult($queryRow)
    {
        return app(EntityManagerInterface::class)->find(AbstractNotification::class, $queryRow['id']);
    }
}
