<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Lädt herunterladbare Attachments für Zotero-Items.
 *
 * Nur Attachments, bei denen Library und Parent-Item download_attachments erlauben.
 * CE-Ebene (zotero_download_attachments) wird im Template geprüft.
 */
final class ZoteroAttachmentResolver
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Lädt Attachments für die angegebenen Item-IDs.
     * Filtert nach Library- und Item-download_attachments.
     *
     * @param array<int>         $itemIds         tl_zotero_item.id
     * @param list<string>|null  $contentTypesFilter Optional: nur diese Content-Types (leer = alle)
     * @param string|null        $filenameMode    Optional: original, cleaned, zotero_key, attachment_id (für Content-Disposition)
     *
     * @return array<int, list<array{id: int, title: string, filename: string, url: string, extension: string}>>
     */
    public function getDownloadableAttachmentsForItems(Connection $connection, array $itemIds, ?array $contentTypesFilter = null, ?string $filenameMode = null): array
    {
        if ($itemIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, \count($itemIds), '?'));
        $params = [1, 1, 0, 1, 1, 0, ...$itemIds, 0];

        $contentTypeCondition = '';
        if ($contentTypesFilter !== null && $contentTypesFilter !== []) {
            $ctPlaceholders = implode(',', array_fill(0, \count($contentTypesFilter), '?'));
            $contentTypeCondition = ' AND a.content_type IN (' . $ctPlaceholders . ')';
            $params = array_merge(array_slice($params, 0, -1), $contentTypesFilter, [0]);
        }

        $rows = $connection->fetchAllAssociative(
            'SELECT a.id, a.pid AS item_id, a.title, a.filename
             FROM tl_zotero_item_attachment a
             INNER JOIN tl_zotero_item i ON i.id = a.pid
               AND i.download_attachments = ?
               AND i.published = ?
               AND i.trash = ?
             INNER JOIN tl_zotero_library l ON l.id = i.pid
               AND l.download_attachments = ?
               AND l.published = ?
             WHERE a.pid IN (' . $placeholders . ') AND a.trash = ?' . $contentTypeCondition . '
             ORDER BY a.sorting ASC, a.id ASC',
            $params
        );

        $byItem = [];
        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $id = (int) $row['id'];
            $filename = (string) ($row['filename'] ?? '');
            $ext = $filename !== '' ? strtolower((string) pathinfo($filename, \PATHINFO_EXTENSION)) : '';
            $urlParams = ['id' => $id];
            if ($filenameMode !== null && $filenameMode !== '') {
                $urlParams['filename_mode'] = $filenameMode;
            }
            $byItem[$itemId][] = [
                'id' => $id,
                'title' => (string) ($row['title'] ?? ''),
                'filename' => $filename,
                'url' => $this->urlGenerator->generate('zotero_attachment', $urlParams, UrlGeneratorInterface::ABSOLUTE_PATH),
                'extension' => $ext !== '' ? $ext : 'xl',
            ];
        }

        return $byItem;
    }

    /**
     * Liefert die Gesamtanzahl der Attachments pro Item (ohne Download-Filter).
     *
     * @param array<int> $itemIds tl_zotero_item.id
     *
     * @return array<int, int> itemId => Anzahl
     */
    public function getTotalAttachmentCountsForItems(Connection $connection, array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, \count($itemIds), '?'));
        $rows = $connection->fetchAllAssociative(
            'SELECT pid AS item_id, COUNT(*) AS cnt FROM tl_zotero_item_attachment WHERE pid IN (' . $placeholders . ') GROUP BY pid',
            $itemIds
        );

        $byItem = [];
        foreach ($rows as $row) {
            $byItem[(int) $row['item_id']] = (int) $row['cnt'];
        }

        return $byItem;
    }
}
