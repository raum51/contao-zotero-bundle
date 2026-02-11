<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Liefert Zotero-Items als .bib-Datei (gespeichertes bib_content).
 *
 * Liegt in src/Controller/, da es HTTP-Anfragen für das Frontend beantwortet.
 * Zugriff nur auf publizierte Items und publizierte Libraries (Blueprint 5.5).
 */
final class BibExportController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Einzelnes Item als .bib ausliefern.
     *
     * Route: GET /zotero/export/item/{id}.bib
     * {id} kann numerische ID oder alias (cite_key) sein, z. B. najm_associations_2020
     */
    public function item(string $id): Response
    {
        if (is_numeric($id)) {
            $row = $this->connection->fetchAssociative(
                'SELECT i.bib_content, i.title
                 FROM tl_zotero_item i
                 INNER JOIN tl_zotero_library l ON l.id = i.pid
                 WHERE i.id = ? AND i.published = ? AND l.published = ?',
                [(int) $id, '1', '1']
            );
        } else {
            $row = $this->connection->fetchAssociative(
                'SELECT i.bib_content, i.title
                 FROM tl_zotero_item i
                 INNER JOIN tl_zotero_library l ON l.id = i.pid
                 WHERE i.alias = ? AND i.published = ? AND l.published = ?',
                [$id, '1', '1']
            );
        }

        if ($row === false || ($row['bib_content'] ?? '') === '') {
            throw new NotFoundHttpException('Item not found or not published.');
        }

        $content = (string) $row['bib_content'];
        $filename = $this->sanitizeFilename((string) $row['title']) . '.bib';

        return $this->bibResponse($content, $filename);
    }

    /**
     * Liste von Items als eine .bib-Datei.
     *
     * Route: GET /zotero/export/list.bib
     * Query: ids=1,2,3 ODER collection=123 ODER library=1
     */
    public function list(Request $request): Response
    {
        $ids = $this->resolveItemIds($request);
        if ($ids === []) {
            throw new NotFoundHttpException('No valid ids, collection or library specified.');
        }

        $placeholders = implode(',', array_fill(0, \count($ids), '?'));
        $rows = $this->connection->fetchAllAssociative(
            "SELECT i.bib_content
             FROM tl_zotero_item i
             INNER JOIN tl_zotero_library l ON l.id = i.pid
             WHERE i.id IN ($placeholders) AND i.published = '1' AND l.published = '1'",
            $ids
        );

        $parts = [];
        foreach ($rows as $row) {
            $bib = $row['bib_content'] ?? '';
            if ($bib !== '') {
                $parts[] = $bib;
            }
        }

        if ($parts === []) {
            throw new NotFoundHttpException('No published items found.');
        }

        $content = implode("\n\n", $parts);
        $filename = 'zotero-export.bib';

        return $this->bibResponse($content, $filename);
    }

    /**
     * Item-IDs aus Request ermitteln: ids=1,2,3 | collection=123 | library=1
     *
     * @return list<int>
     */
    private function resolveItemIds(Request $request): array
    {
        $idsParam = $request->query->get('ids', '');
        if ($idsParam !== '') {
            $ids = array_map('intval', array_filter(explode(',', (string) $idsParam)));
            return array_values(array_unique($ids));
        }

        $collectionId = $request->query->getInt('collection');
        if ($collectionId > 0) {
            $rows = $this->connection->fetchFirstColumn(
                'SELECT ci.item_id FROM tl_zotero_collection_item ci
                 INNER JOIN tl_zotero_item i ON i.id = ci.item_id
                 INNER JOIN tl_zotero_library l ON l.id = i.pid
                 WHERE ci.collection_id = ? AND i.published = ? AND l.published = ?',
                [$collectionId, '1', '1']
            );
            return array_map('intval', $rows);
        }

        $libraryId = $request->query->getInt('library');
        if ($libraryId > 0) {
            $rows = $this->connection->fetchFirstColumn(
                'SELECT i.id FROM tl_zotero_item i
                 INNER JOIN tl_zotero_library l ON l.id = i.pid
                 WHERE i.pid = ? AND i.published = ? AND l.published = ?',
                [$libraryId, '1', '1']
            );
            return array_map('intval', $rows);
        }

        return [];
    }

    private function bibResponse(string $content, string $filename): Response
    {
        $response = new Response($content, 200, [
            'Content-Type' => 'application/x-bibtex; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $this->escapeFilename($filename) . '"',
        ]);
        $response->headers->set('Cache-Control', 'private, no-cache');

        return $response;
    }

    private function sanitizeFilename(string $title): string
    {
        $s = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $title);
        $s = preg_replace('/\s+/', '-', trim($s));
        return $s !== '' ? substr($s, 0, 200) : 'item';
    }

    private function escapeFilename(string $filename): string
    {
        return str_replace(['"', '\\'], ['%22', '\\\\'], $filename);
    }
}
