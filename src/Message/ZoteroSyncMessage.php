<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Message;

use Contao\CoreBundle\Messenger\Message\LowPriorityMessageInterface;

/**
 * Message für den asynchronen Zotero-Sync über den Symfony Messenger.
 *
 * Liegt in src/Message/, da Messages als eigenständige DTOs Teil des Messenger-Patterns sind.
 * LowPriorityMessageInterface sorgt für Routing auf contao_prio_low (WebWorker/Cron).
 *
 * @internal Für Backend-Sync: ZoteroLibrarySyncCallback erstellt Job (falls 5.6+), dispatcht diese Message.
 */
final class ZoteroSyncMessage implements LowPriorityMessageInterface
{
    public function __construct(
        /** Library-ID oder null für alle publizierten Libraries */
        public readonly ?int $libraryId,
        /** Bei true: Sync-State vor dem Sync zurücksetzen */
        public readonly bool $resetFirst = false,
        /** Job-UUID (ab Contao 5.6) für Fortschritts-Anzeige im Backend. Null wenn kein Job-Framework. */
        public readonly ?string $jobUuid = null,
    ) {
    }
}
