<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Legt die App-config/routes.yaml an bzw. ergänzt sie um den Import der Bundle-Routen.
 *
 * Liegt in src/Command/, da es ein CLI-Hilfsbefehl für die Installation ist.
 * Nach "composer require raum51/contao-zotero-bundle" einmal ausführen, damit
 * die Frontend-Routen (Bib-Export, Attachment-Proxy) geladen werden.
 */
#[AsCommand(
    name: 'contao:zotero:install-routes',
    description: 'Fügt den Zotero-Bundle-Routen-Import in config/routes.yaml ein (oder legt die Datei an).',
)]
final class InstallRoutesCommand extends Command
{
    private const ROUTES_IMPORT_MARKER = 'Raum51ContaoZoteroBundle';
    private const ROUTES_BLOCK = <<<'YAML'

# Zotero-Bundle (Bib-Export, Attachment-Proxy) – von contao:zotero:install-routes eingetragen
Raum51ContaoZoteroBundle:
    resource: '@Raum51ContaoZoteroBundle/Resources/config/routes.yaml'
YAML;

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $configDir = $this->kernel->getProjectDir() . '/config';
        $routesFile = $configDir . '/routes.yaml';

        if (!is_dir($configDir)) {
            $io->error('Verzeichnis config/ nicht gefunden (Projekt-Root: ' . $this->kernel->getProjectDir() . ').');

            return self::FAILURE;
        }

        $content = is_file($routesFile) ? (string) file_get_contents($routesFile) : '';

        if (str_contains($content, self::ROUTES_IMPORT_MARKER)) {
            $io->success('Der Routen-Import für das Zotero-Bundle ist in config/routes.yaml bereits vorhanden.');

            return self::SUCCESS;
        }

        $newContent = $content === '' ? trim(self::ROUTES_BLOCK) : $content . self::ROUTES_BLOCK;

        if (!file_put_contents($routesFile, $newContent)) {
            $io->error('Konnte config/routes.yaml nicht schreiben.');

            return self::FAILURE;
        }

        $io->success('config/routes.yaml wurde angelegt bzw. um den Zotero-Bundle-Routen-Import ergänzt. Cache leeren nicht vergessen (z. B. php bin/console cache:clear).');

        return self::SUCCESS;
    }
}
