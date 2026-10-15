<?php

namespace Concrete\Package\NotificationManagerKit\Controller\Search;

use Concrete\Core\Search\StickyRequest;
use Concrete\Core\Controller\AbstractController;
use NotificationManagerKit\Search\ItemList\Notifications as SearchList;
use NotificationManagerKit\Search\Result\Notifications as SearchResult;
use NotificationManagerKit\Search\Column\Set\Notifications as ColumnSet;

class Notifications extends AbstractController
{
    private $stickyRequest;

    private $searchList;

    private $searchResult;

    protected function getSearchList()
    {
        if ($this->searchList === null) {
            $this->searchList = $this->app->make(SearchList::class, [$this->getStickyRequest()]);
        }

        return $this->searchList;
    }

    public function getStickyRequest()
    {
        if ($this->stickyRequest === null) {
            $this->stickyRequest = new StickyRequest('nm.notifications');
        }

        return $this->stickyRequest;
    }

    public function getAllowedPaginationSizes()
    {
        return [10, 20, 50, 100];
    }

    public function getDefaultPaginationSize()
    {
        return $this->getAllowedPaginationSizes()[1];
    }

    public function search($reset = false)
    {
        $stickyRequest = $this->getStickyRequest();
        $searchList = $this->getSearchList();

        if ($reset) {
            $stickyRequest->resetSearchRequest();
        }

        $req = $stickyRequest->getSearchRequest();
        $columnSet = new ColumnSet();

        if (!$searchList->getActiveSortColumn()) {
            $sortColumn = $columnSet->getDefaultSortColumn();
            $searchList->sanitizedSortBy($sortColumn->getColumnKey(), $sortColumn->getColumnDefaultSortDirection());
        }

        $q = $req['name'] ?? null;
        if (is_string($q) && $q !== '') {
            $searchList->filterByName($q);
        }

        $q = $req['type'] ?? null;
        if (is_string($q) && $q !== '') {
            $searchList->filterByType($q);
        }

        $paginationSize = $this->getDefaultPaginationSize();
        $q = $req['num_results'] ?? null;
        if ($q && $this->app->make('helper/validation/numbers')->integer($q) && in_array((int) $q, $this->getAllowedPaginationSizes(), true)) {
            $paginationSize = (int) $q;
        }

        $searchList->setItemsPerPage($paginationSize);

        $this->searchResult = new SearchResult(
            $columnSet,
            $searchList,
            $this->app->make('url/manager')->resolve(['dashboard/notification_manager/notifications/']),
        );
    }

    /**
     * @return SearchResult|null
     */
    public function getSearchResultObject()
    {
        return $this->searchResult;
    }
}
