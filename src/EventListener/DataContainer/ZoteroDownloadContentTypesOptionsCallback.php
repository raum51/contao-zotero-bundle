<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert Content-Types aus tl_zotero_item_attachment für Download-Filter.
 *
 * SELECT DISTINCT content_type für die Subpalette „Attachments herunterladbar“.
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback ist.
 */
#[AsCallback(table: 'tl_content', target: 'fields.zotero_download_content_types.options')]
final class ZoteroDownloadContentTypesOptionsCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, string> content_type => content_type
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT content_type FROM tl_zotero_item_attachment WHERE content_type != ? ORDER BY content_type',
            ['']
        );

        $options = [];
        foreach ($rows as $ct) {
            $ct = (string) $ct;
            if ($ct !== '') {
                $options[$ct] = $ct;
            }
        }

        return $options;
    }
}
