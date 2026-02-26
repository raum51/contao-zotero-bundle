<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

/**
 * Konvertiert leere oder String-0-Werte bei member_id zu 0.
 * Contao-typisch: 0 = „nicht zugeordnet“ (statt NULL).
 */
#[AsCallback(table: 'tl_zotero_creator_map', target: 'fields.member_id.save')]
final class CreatorMapMemberIdSaveCallback
{
    public function __invoke(mixed $value, DataContainer $dc): mixed
    {
        if ($value === '' || $value === '0') {
            return 0;
        }

        return $value;
    }
}
