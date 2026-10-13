<?php

namespace Concrete\Package\NotificationManagerKit;

use Concrete\Core\Entity\Package;
use ClassKit\Package\PackageController;
use Concrete\Core\Support\Facade\Events;
use NotificationManagerKit\Events\NotificationManager;
use ClassKit\Package\Traits\{AttributeTrait, BlockTrait, PageTrait};

class Controller extends PackageController
{
    use AttributeTrait;
    use BlockTrait;
    use PageTrait;
    /**
     * The packages handle.
     * Note that this must be unique in the
     * entire concrete5 package ecosystem.
     *
     * @var string
     */
    protected $pkgHandle = 'notification_manager_kit';

    /**
     * The packages version.
     *
     * @var string
     */
    protected $pkgVersion = '0.1.2';

    /**
     * The minimum Concrete version compatible with the package.
     * Override this value according to the minimum required version for your package.
     *
     * @var string
     */
    protected $appVersionRequired = '9.5.0';

    /**
     * The minimum PHP version compatible with the package.
     * Override this value according to the minimum required version for your package.
     *
     * @var string
     * @var string
     */
    protected $phpVersionRequired = '8.4';

    /**
     * Package service providers to register.
     *
     * eg. 'Concrete\Package\PackageHandle\Src\Providers\PackageServiceProvider'
     *
     * @var array
     */
    protected $providers = [];

    /**
     * An array describing the package dependencies.
     * Keys are package handles.
     * Values may be:
     * - false: this package can't be installed if the other package is already installed.
     * - true: this package can't be installed of the other package is not installed
     * - a string: this package can't be installed of the other package is not installed or it's installed with an older version
     * - an array with two strings, representing the minimum and the maximum version of the other package to be installed.
     *
     * @var array
     *
     * @example [
     *     // This package can't be installed if a package with handle other_package_1 is already installed.
     *     'other_package_1' => false,
     *     // This package can't be installed if a package with handle other_package_2 is not installed.
     *     'other_package_2' => true,
     *     // This package can't be installed if a package with handle other_package_3 is not installed, or it has a version prior to 1.0
     *     'other_package_3' => '1.0',
     *     // This package can't be installed if a package with handle other_package_4 is not installed, or it has a version prior to 2.0, or it has a version after 2.9
     *     'other_package_4' => ['2.0', '2.9'],
     * ]
     */
    protected $packageDependencies = [
        'class_kit' => true,
    ];

    /**
     * Package class autoloader registrations
     * The package install helper class, included with this boilerplate,
     * is activated by default.
     *
     * @see https://goo.gl/4wyRtH
     * @var array
     */
    protected $pkgAutoloaderRegistries = [
        'src' => '\NotificationManagerKit',
    ];

    /**
     * Tables dropped on uninstall when "purge database tables" is ticked, children before parents.
     *
     * @var array
     */
    protected $databaseTables = [
        'nmNotification_Contents',
        'nmNotificationPages',
        'nmNotificationContents',
        'nmNotifications',
        'nmSettings',
    ];

    /**
     * Package tasks to register.
     *
     * eg. 'task_handle' => \PackageHandle\Command\Task\Controller\TaskHandleController::class,
     *
     * @var array
     */
    protected $tasks = [];

    public function getPackageName()
    {
        return t('Notification Manager');
    }

    public function getPackageDescription()
    {
        return t('Add banners and popups to your site, with scheduling, page targeting and per-locale content.');
    }

    public function on_start()
    {
        parent::on_start();

        Events::addListener('on_before_render', function () {
            NotificationManager::onBeforeRender();
        });
    }

    public function installOrUpgrade(Package $pkg)
    {
        $this->addSinglePage('/dashboard/notification_manager', $pkg, t('Notification Manager'), t('Manage banners and popups'));
        $this->addSinglePage('/dashboard/notification_manager/notifications', $pkg, t('Notifications'), t('Banners and popups'));
        $this->addSinglePage('/dashboard/notification_manager/settings', $pkg, t('Settings'), t('Notification Manager settings'));

        \NotificationManagerKit\Support\Catalog::flush();
    }

    public function registerRoutes(): void {}

    public function registerEvents(): void {}
}
