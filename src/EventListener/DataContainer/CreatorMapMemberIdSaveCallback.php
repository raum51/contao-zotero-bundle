<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

/**
 * Konvertiert leere oder 0-Werte bei member_id zu null.
 * Ermöglicht NULL als semantisch korrekten „nicht zugeordnet“-Zustand.
 */
#[AsCallback(table: 'tl_zotero_creator_map', target: 'fields.member_id.save')]
final class CreatorMapMemberIdSaveCallback
{
    public function __invoke(mixed $value, DataContainer $dc): mixed
    {
        if ($value === '' || $value === 0 || $value === '0') {
            return null;
        }

        return $value;
    }
}
