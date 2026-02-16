<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller;

use Contao\CoreBundle\Slug\Slug;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Raum51\ContaoZoteroBundle\Service\ZoteroClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
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
    private const DOWNLOAD_FILENAME_MAX_LENGTH_CLEANED = 100;

    private const VALID_FILENAME_MODES = ['original', 'cleaned', 'zotero_key', 'attachment_id'];

    /** MIME-Type → Dateiendung für Fallback wenn kein filename vorhanden */
    private const CONTENT_TYPE_EXTENSION_MAP = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/plain' => 'txt',
        'text/html' => 'html',
        'application/zip' => 'zip',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(
        private readonly ZoteroClient $zoteroClient,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly Slug $slug,
    ) {
    }

    /**
     * Attachment-Datei per ID (tl_zotero_item) streamen.
     *
     * Item muss item_type = "attachment" sein. Library- und Item-download_attachments
     * müssen erlaubt sein.
     *
     * Route: GET /zotero/attachment/{id}
     *
     * Optionale Query-Parameter: filename_mode (original|cleaned|zotero_key|attachment_id)
     */
    public function download(int $id, Request $request): StreamedResponse|RedirectResponse
    {
        $item = $this->connection->fetchAssociative(
            'SELECT a.id, a.pid AS item_id, a.zotero_key, a.title, a.filename, a.content_type,
                    a.link_mode, a.url AS attachment_url,
                    i.download_attachments AS item_download_attachments,
                    l.id AS library_id, l.library_id AS zotero_library_id, l.library_type, l.api_key,
                    l.download_attachments AS library_download_attachments
             FROM tl_zotero_item_attachment a
             INNER JOIN tl_zotero_item i ON i.id = a.pid
             INNER JOIN tl_zotero_library l ON l.id = i.pid
             WHERE a.id = ? AND i.published = ? AND l.published = ?',
            [$id, '1', '1']
        );

        if ($item === false) {
            throw new NotFoundHttpException('Attachment not found or not published.');
        }

        if (($item['library_download_attachments'] ?? '') !== '1') {
            throw new NotFoundHttpException('Downloads not allowed for this library.');
        }
        if (($item['item_download_attachments'] ?? '') !== '1') {
            throw new NotFoundHttpException('Downloads not allowed for this item.');
        }

        $prefix = ($item['library_type'] ?? 'user') === 'group'
            ? 'groups/' . ($item['zotero_library_id'] ?? '')
            : 'users/' . ($item['zotero_library_id'] ?? '');
        $path = $prefix . '/items/' . ($item['zotero_key'] ?? '') . '/file';
        $apiKey = (string) ($item['api_key'] ?? '');

        $this->logger->info('Zotero attachment download', [
            'attachment_id' => $id,
            'item_id' => $item['item_id'] ?? null,
            'library_id' => $item['library_id'] ?? null,
            'path' => $path,
        ]);

        // linked_url: Datei liegt nicht bei Zotero, nur Link-Metadaten → Redirect zur gespeicherten URL
        $linkMode = (string) ($item['link_mode'] ?? '');
        if ($linkMode === 'linked_url') {
            $redirectUrl = trim((string) ($item['attachment_url'] ?? ''));
            if ($redirectUrl !== '' && str_starts_with($redirectUrl, 'http')) {
                return new RedirectResponse($redirectUrl, Response::HTTP_FOUND);
            }
            $this->logger->warning('Zotero attachment linked_url without valid URL', [
                'attachment_id' => $id,
            ]);
            throw new NotFoundHttpException('Attachment URL not available.');
        }

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

        $filenameMode = $request->query->get('filename_mode', 'cleaned');
        if (!\in_array($filenameMode, self::VALID_FILENAME_MODES, true)) {
            $filenameMode = 'cleaned';
        }
        $downloadFilename = $this->buildDownloadFilename(
            $filenameMode,
            $id,
            (string) ($item['filename'] ?? ''),
            (string) ($item['zotero_key'] ?? ''),
            (string) ($item['content_type'] ?? '')
        );
        $headers['Content-Disposition'] = sprintf(
            'attachment; filename="%s"',
            str_replace(['"', '\\'], '', $downloadFilename)
        );

        // Streamen: toStream() liefert einen PHP-Stream; mit fpassthru() korrekt ausgeben.
        // foreach über einen Stream-Ressource funktioniert nicht (liefert leere Downloads).
        if (method_exists($response, 'toStream')) {
            $streamed = new StreamedResponse(function () use ($response): void {
                $stream = $response->toStream(false);
                if (\is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            }, $statusCode, $headers);
        } else {
            $streamed = new StreamedResponse(function () use ($response): void {
                echo $response->getContent(false);
            }, $statusCode, $headers);
        }
        $streamed->headers->set('Cache-Control', 'private, no-cache');

        return $streamed;
    }

    /**
     * Dateinamen für Download je nach Mode.
     * Fallback bei fehlendem filename: zotero_key, sonst attachment_id.
     */
    private function buildDownloadFilename(
        string $mode,
        int $attachmentId,
        string $filename,
        string $zoteroKey,
        string $contentType
    ): string {
        $ext = $this->getExtensionFromFilenameOrContentType($filename, $contentType);

        switch ($mode) {
            case 'original':
                if (trim($filename) !== '') {
                    return $filename;
                }
                $base = $zoteroKey !== '' ? $zoteroKey : (string) $attachmentId;
                return $base . $ext;
            case 'zotero_key':
                $base = $zoteroKey !== '' ? $zoteroKey : (string) $attachmentId;
                return $base . $ext;
            case 'attachment_id':
                return (string) $attachmentId . $ext;
            case 'cleaned':
            default:
                $base = trim($filename) !== '' ? $this->extractBaseWithoutExtension($filename) : $zoteroKey;
                if ($base === '' && $zoteroKey !== '') {
                    $base = $zoteroKey;
                } elseif ($base === '') {
                    $base = (string) $attachmentId;
                }
                $slugified = $this->slug->generate($base, [], static fn (): bool => false);
                $slugified = rtrim(mb_substr($slugified, 0, self::DOWNLOAD_FILENAME_MAX_LENGTH_CLEANED), '-');
                if ($slugified === '') {
                    $slugified = $zoteroKey !== '' ? $zoteroKey : (string) $attachmentId;
                }
                return $slugified . $ext;
        }
    }

    private function extractBaseWithoutExtension(string $filename): string
    {
        $pos = strrpos($filename, '.');
        if ($pos !== false && $pos > 0) {
            return substr($filename, 0, $pos);
        }
        return $filename;
    }

    private function getExtensionFromFilenameOrContentType(string $filename, string $contentType): string
    {
        $pos = strrpos($filename, '.');
        if ($pos !== false && $pos > 0) {
            return substr($filename, $pos);
        }
        if (trim($contentType) !== '') {
            $mapped = self::CONTENT_TYPE_EXTENSION_MAP[$contentType] ?? null;
            if ($mapped !== null) {
                return '.' . $mapped;
            }
        }
        return '';
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
