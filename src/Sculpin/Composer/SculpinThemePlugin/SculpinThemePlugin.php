<?php

namespace Sculpin\Composer\SculpinThemePlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\PackageEvent;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\Finder;

class SculpinThemePlugin implements PluginInterface, EventSubscriberInterface
{
    private $composer;
    private $io;
    private $themeDir;
    private $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::PRE_PACKAGE_INSTALL => array(
                'onPrePackageInstall',
            ),
            ScriptEvents::PRE_PACKAGE_UPDATE => array(
                'onPrePackageUpdate',
            ),
            ScriptEvents::POST_PACKAGE_INSTALL => array(
                'onPostPackageInstall',
            ),
            ScriptEvents::POST_PACKAGE_UPDATE => array(
                'onPostPackageUpdate',
            ),
            ScriptEvents::POST_PACKAGE_UNINSTALL => array(
                'onPostPackageUninstall',
            ),
        );
    }

    public function onPrePackageInstall(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getPackage();
        $this->removeThemeFiles($package);
    }

    public function onPrePackageUpdate(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getInitialPackage();
        $this->removeThemeFiles($package);
    }

    public function onPostPackageInstall(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getPackage();
        $this->installThemeFiles($package);
    }

    public function onPostPackageUpdate(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getTargetPackage();
        $this->installThemeFiles($package);
    }

    public function onPostPackageUninstall(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getPackage();
        $this->removeThemeFiles($package);
    }

    private function getThemeDir()
    {
        if (null !== $this->themeDir) {
            return $this->themeDir;
        }

        $config = $this->composer->getConfig();

        return $this->themeDir = $config->has('theme-dir') ? $config->get('theme-dir') : 'source/themes';
    }

    private function findTheme(PackageInterface $package)
    {
        $extra = $package->getExtra();

        if (isset($extra['sculpin-theme'])) {
            $theme = $extra['sculpin-theme'];

            if (! isset($theme['name'])) {
                throw new \RuntimeException(
                    sprintf('Package %s has sculpin-theme configuration but provided no theme name', $package->getName())
                );
            }

            $theme['installation-path'] = $this->getThemeDir() . '/' . $theme['name'];

            return $theme;
        }

        if ('sculpin-theme' === $package->getType()) {
            throw new \RuntimeException(
                sprintf('Package %s was declared as sculpin-theme but provided no theme name', $package->getName())
            );
        }
    }

    private function installThemeFiles(PackageInterface $package)
    {
        $theme = $this->findTheme($package);

        if (! $theme) {
            return;
        }

        $themeSource = $this->composer->getInstallationManager()->getInstallPath($package);
        $themeTarget = $theme['installation-path'];

        $this->filesystem->ensureDirectoryExists($themeTarget);

        $finder = new Finder();
        $files = $finder
            ->ignoreVCS(true)
            ->in($themeSource);

        foreach ($files as $file) {
            $targetPath = $themeTarget . DIRECTORY_SEPARATOR . $file->getRelativePathname();
            if ($file->isDir()) {
                $this->filesystem->ensureDirectoryExists($targetPath);
            } else {
                copy($file->getPathname(), $targetPath);
            }
        }
    }

    private function removeThemeFiles(PackageInterface $package)
    {
        $theme = $this->findTheme($package);

        if (! $theme) {
            return;
        }

        $themeTarget = $theme['installation-path'];

        $this->filesystem->remove($themeTarget);
    }
}
