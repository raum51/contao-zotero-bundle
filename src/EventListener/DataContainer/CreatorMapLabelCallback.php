<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Formatiert das Listen-Label für tl_zotero_creator_map.
 * Zeigt „Vorname Nachname → Member-Name“ bzw. „– nicht zugeordnet –“.
 */
#[AsCallback(table: 'tl_zotero_creator_map', target: 'list.label.label_callback')]
final class CreatorMapLabelCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(array $row, string $label, DataContainer $dc, array $args = []): string
    {
        $first = trim((string) ($row['zotero_firstname'] ?? ''));
        $last = trim((string) ($row['zotero_lastname'] ?? ''));
        $creatorName = $first !== '' || $last !== ''
            ? trim($first . ' ' . $last)
            : 'ID ' . ($row['id'] ?? '');

        $memberId = (int) ($row['member_id'] ?? 0);
        if ($memberId < 1) {
            return $creatorName . ' → – nicht zugeordnet –';
        }

        $member = $this->connection->fetchAssociative(
            'SELECT firstname, lastname FROM tl_member WHERE id = ?',
            [$memberId]
        );

        if ($member === false) {
            return $creatorName . ' → ID ' . $memberId . ' (nicht gefunden)';
        }

        $firstM = trim((string) ($member['firstname'] ?? ''));
        $lastM = trim((string) ($member['lastname'] ?? ''));
        $memberName = $firstM !== '' || $lastM !== ''
            ? trim($firstM . ' ' . $lastM)
            : 'ID ' . $memberId;

        return $creatorName . ' → ' . $memberName;
    }
}
