<?php defined('C5_EXECUTE') or die('Access Denied.');

if (in_array($controller->getTask(), ['add', 'details', 'save'], true)) {
    View::element(
        'dashboard/notifications/details',
        [
            'token' => $token,
            'entity' => $entity ?? null,
            'locales' => $locales,
            'types' => $types,
            'scopes' => $scopes,
            'repeatEvents' => $repeatEvents,
            'transitions' => $transitions,
            'frequencies' => $frequencies,
            'scrollUnits' => $scrollUnits,
            'linkTypes' => $linkTypes,
            'h' => [
                'al' => $concrete_asset_library,
                'ps' => $form_page_selector,
                'dt' => $form_date_time,
            ],
            'v' => $v,
        ],
        'notification_manager_kit',
    );
} else {
    View::element(
        'dashboard/notifications/all',
        [
            'items' => $items,
            'result' => $result,
            'pagination' => $pagination,
        ],
        'notification_manager_kit',
    );
}
