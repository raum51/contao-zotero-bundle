<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Raum51\ContaoZoteroBundle\Service\ZoteroClient;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streamt Attachment-Dateien von der Zotero-API (kein lokales Caching).
 *
 * Liegt in src/Controller/, da es HTTP-Anfragen für das Frontend beantwortet.
 * Prüfung der Ebenen Library und Item für download_attachments (Blueprint 4);
 * Modul-Ebene kommt mit den Frontend-Modulen in Phase 4.
 */
final class AttachmentProxyController
{
    public function __construct(
        private readonly ZoteroClient $zoteroClient,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Attachment-Datei per ID (tl_zotero_item) streamen.
     *
     * Item muss item_type = "attachment" sein. Library- und Item-download_attachments
     * müssen erlaubt sein.
     *
     * Route: GET /zotero/attachment/{id}
     */
    public function download(int $id): StreamedResponse
    {
        $item = $this->connection->fetchAssociative(
            'SELECT i.id, i.pid, i.zotero_key, i.download_attachments, i.title
             FROM tl_zotero_item i
             INNER JOIN tl_zotero_library l ON l.id = i.pid
             WHERE i.id = ? AND i.published = ? AND l.published = ?',
            [$id, '1', '1']
        );

        if ($item === false) {
            throw new NotFoundHttpException('Item not found or not published.');
        }

        $itemType = $this->connection->fetchOne('SELECT item_type FROM tl_zotero_item WHERE id = ?', [$id]);
        if (strtolower((string) $itemType) !== 'attachment') {
            throw new NotFoundHttpException('Item is not an attachment.');
        }

        $library = $this->connection->fetchAssociative(
            'SELECT id, library_id, library_type, api_key, download_attachments FROM tl_zotero_library WHERE id = ?',
            [$item['pid']]
        );
        if ($library === false || ($library['download_attachments'] ?? '') !== '1') {
            throw new NotFoundHttpException('Downloads not allowed for this library.');
        }
        if (($item['download_attachments'] ?? '') !== '1') {
            throw new NotFoundHttpException('Downloads not allowed for this item.');
        }

        $prefix = ($library['library_type'] ?? 'user') === 'group'
            ? 'groups/' . ($library['library_id'] ?? '')
            : 'users/' . ($library['library_id'] ?? '');
        $path = $prefix . '/items/' . ($item['zotero_key'] ?? '') . '/file';
        $apiKey = (string) ($library['api_key'] ?? '');

        $this->logger->info('Zotero attachment download', [
            'item_id' => $id,
            'library_id' => $library['id'],
            'path' => $path,
        ]);

        $response = $this->zoteroClient->request('GET', $path, $apiKey);

        try {
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->error('Zotero attachment request failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw new NotFoundHttpException('Attachment unavailable.', $e);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->warning('Zotero attachment non-OK status', ['path' => $path, 'status' => $statusCode]);
            throw new NotFoundHttpException('Attachment unavailable.');
        }

        $headers = $this->responseHeadersFromZotero($response);
        if ($headers === []) {
            $headers['Content-Type'] = 'application/octet-stream';
        }

        // Streamen, falls die Response toStream() unterstützt (Symfony HttpClient)
        if (method_exists($response, 'toStream')) {
            $streamed = new StreamedResponse(function () use ($response): void {
                foreach ($response->toStream(false) as $chunk) {
                    echo $chunk;
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
            }, $statusCode, $headers);
            $streamed->headers->set('Cache-Control', 'private, no-cache');
            return $streamed;
        }

        $streamed = new StreamedResponse(function () use ($response): void {
            echo $response->getContent(false);
        }, $statusCode, $headers);
        $streamed->headers->set('Cache-Control', 'private, no-cache');
        return $streamed;
    }

    /**
     * Content-Type und Content-Disposition aus der Zotero-Response übernehmen.
     *
     * @return array<string, string>
     */
    private function responseHeadersFromZotero(object $response): array
    {
        $out = [];
        if (!method_exists($response, 'getHeaders')) {
            return $out;
        }
        $headers = $response->getHeaders(false);
        if (isset($headers['content-type'][0])) {
            $out['Content-Type'] = $headers['content-type'][0];
        }
        if (isset($headers['content-disposition'][0])) {
            $out['Content-Disposition'] = $headers['content-disposition'][0];
        }
        return $out;
    }
}
