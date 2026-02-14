<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * Liefert Zotero-Einzelelemente (type=zotero_item) im Modus from_url als Optionen.
 * Nur diese dürfen für den Reader-Modus (News-Pattern) referenziert werden.
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback für tl_content ist.
 */
#[AsCallback(table: 'tl_content', target: 'fields.zotero_reader_element.options')]
final class ZoteroReaderElementOptionsCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<int, string> id => "Titel / Headline (ID)"
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, title, headline FROM tl_content
             WHERE type = 'zotero_item' AND zotero_item_mode = 'from_url'
             ORDER BY id DESC"
        );

        $options = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $title = trim((string) ($row['title'] ?? ''));
            $headline = $row['headline'] ?? '';
            $data = StringUtil::deserialize($headline, true);
            $headlineText = \is_array($data) ? (string) ($data['value'] ?? '') : '';
            $headlineText = trim((string) $headlineText);
            $label = $title !== '' ? $title : ($headlineText !== '' ? $headlineText : 'CE ' . $id);
            $options[$id] = $label . ' (' . $id . ')';
        }

        return $options;
    }
}
