<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Twig;

use Symfony\Bridge\Twig\TokenParser\DumpTokenParser;
use Twig\Extension\AbstractExtension;

/**
 * Stellt den {% dump %}-Tag in allen Umgebungen bereit.
 *
 * In Produktion ist der Tag verfügbar (kein "Unknown tag" Fehler), führt aber nichts aus,
 * da Symfony's DumpNode intern env->isDebug() prüft. Nur in dev wird gedumpt.
 *
 * Liegt unter Twig/, da es eine Twig-Extension ist.
 */
final class ZoteroDumpExtension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [new DumpTokenParser()];
    }
}
