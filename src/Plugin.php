<?php

namespace Winter\PackageControl;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PrePoolCreateEvent;
use Composer\Repository\ComposerRepository;
use Winter\PackageControl\Exception\UnapprovedPackageException;

/**
 * Package Control plugin.
 *
 * This plugin acts as a gatekeeper for Winter CMS plugins, themes and modules, by allowing these packages to be
 * installed only if they meet one of the following requirements:
 *
 *  - They have been approved by the Winter CMS maintainers and are delivered through the Winter CMS Composer
 *    repository.
 *  - The plugin is sourced from a non-Packagist repository, ie. a private repository, or through GitHub or another
 *    applicable source-code control source.
 *  - The plugin is locally stored.
 *
 * This essentially stops plugins, themes and modules from being installed via Packagist, in which anyone can publish
 * packages, unless the developer explicitly allows it.
 *
 * A Winter CMS installation can allow Packagist packages to be installed by adding the following to the `extra` section
 * in their `composer.json`.
 *
 * ```json
 * {
 *     "extra": {
 *         "winter": {
 *             "allowPackagist": true
 *         }
 *     }
 * }
 * ```
 *
 * @author Ben Thomson <git@alfreido.com>
 * @copyright 2023 Winter CMS.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var boolean Determines if Winter plugins/themes are allowed from Packagist.
     */
    protected $disabled = false;

    /**
     * @var array Packages that are always allowed to be installed.
     */
    protected $allowedPackages = [
        'winter/wn-backend-module',
        'winter/wn-cms-module',
        'winter/wn-system-module',
    ];

    public function activate(Composer $composer, IOInterface $io)
    {
        $winterConfig = $composer->getPackage()->getExtra()['winter'] ?? [];
        $this->disabled = (array_key_exists('allowPackagist', $winterConfig) && $winterConfig['allowPackagist'] === true);
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        // Nothing to do here
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        // Nothing to do here
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginEvents::PRE_POOL_CREATE => 'onPrePoolCreate',
        ];
    }

    public function onPrePoolCreate(PrePoolCreateEvent $event)
    {
        if ($this->disabled) {
            return;
        }

        $packages = $event->getPackages();

        foreach ($packages as $package) {
            if (in_array($package->getName(), $this->allowedPackages)) {
                continue;
            }
            if (in_array($package->getType(), ['winter-plugin', 'winter-theme', 'winter-module'])) {
                // Determine if source is Packagist - if so, throw an exception
                if (
                    $package->getRepository() instanceof ComposerRepository
                    && str_ends_with($package->getRepository()->getRepoConfig()['url'], 'repo.packagist.org')
                ) {
                    throw new UnapprovedPackageException('Package ' . $package->getName() . ' is not approved by Winter CMS for installation. Please remove it from your requirements in composer.json.');
                }
            }
        }
    }
}
