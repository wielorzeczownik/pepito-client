<?php

declare(strict_types=1);

namespace Pepito\Installer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Fetches the prebuilt native library after install/update.
 */
final class LibraryInstallerPlugin implements EventSubscriberInterface, PluginInterface
{
    public function activate(Composer $composer, IOInterface $io): void {}

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'download',
            ScriptEvents::POST_UPDATE_CMD => 'download',
        ];
    }

    public static function download(Event $event): void
    {
        LibraryDownloader::run($event->getIO());
    }
}
