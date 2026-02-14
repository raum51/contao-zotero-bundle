<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert tl_member für tl_zotero_creator_map.member_id.
 * Format: „Vorname Nachname (E-Mail)“. Option 0 für „nicht zugeordnet“ (Filter).
 */
#[AsCallback(table: 'tl_zotero_creator_map', target: 'fields.member_id.options')]
final class CreatorMapMemberOptionsCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $options = [0 => '– nicht zugeordnet –'];

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, firstname, lastname, email FROM tl_member ORDER BY lastname, firstname'
        );

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $first = trim((string) ($row['firstname'] ?? ''));
            $last = trim((string) ($row['lastname'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));

            $name = $last !== '' ? ($first !== '' ? $last . ', ' . $first : $last) : ($first !== '' ? $first : 'ID ' . $id);
            $label = $email !== '' ? $name . ' (' . $email . ')' : $name;

            $options[$id] = $label;
        }

        return $options;
    }
}
