<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Lädt Stop-Wörter für die Zotero-Volltextsuche.
 *
 * Liegt im Service-Verzeichnis, da es von ZoteroSearchService benötigt wird.
 * Lädt zuerst Projekt-Dateien (config/zotero_stopwords_de.php, config/zotero_stopwords_en.php),
 * falls vorhanden; sonst die Bundle-Dateien.
 */
final class ZoteroStopwordService
{
    private const LANGUAGES = ['de', 'en'];

    /**
     * @var array<string, array<string>>
     */
    private array $cache = [];

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    private function getProjectDir(): string
    {
        return $this->kernel->getProjectDir();
    }

    /**
     * @return array<string>
     */
    public function getStopwords(string $lang): array
    {
        $lang = strtolower($lang);
        if (!\in_array($lang, self::LANGUAGES, true)) {
            return [];
        }

        if (isset($this->cache[$lang])) {
            return $this->cache[$lang];
        }

        $projectFile = $this->getProjectDir() . '/config/zotero_stopwords_' . $lang . '.php';
        $bundleFile = __DIR__ . '/../Resources/stopwords/stopwords-' . $lang . '.php';

        $file = is_file($projectFile) ? $projectFile : $bundleFile;

        if (!is_file($file)) {
            $this->cache[$lang] = [];

            return [];
        }

        $words = include $file;
        $this->cache[$lang] = \is_array($words) ? array_map('strtolower', $words) : [];

        return $this->cache[$lang];
    }

    /**
     * @return array<string>
     */
    public function getStopwordsForLocale(string $locale): array
    {
        $lang = substr($locale, 0, 2);

        return $this->getStopwords($lang);
    }
}
