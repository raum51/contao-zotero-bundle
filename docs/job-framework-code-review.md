# Code-Review: Zotero-Bundle vs. Contao Jobs/Messenger-Doku

Stand: 2026-02-18  
**Quellen:** [Contao Dev – Jobs](https://docs.contao.org/dev/framework/jobs/), [Contao Dev – Async Messaging](https://docs.contao.org/dev/framework/async-messaging/)

---

## 1. Abgleich mit Contao-Dokumentation

### Messenger

| Aspekt | Contao-Doku | Unsere Umsetzung | Status |
|--------|-------------|------------------|--------|
| Message-Routing | LowPriorityMessageInterface → contao_prio_low | ✅ Implementiert + TransportNamesStamp als Fallback | ✅ |
| Handler | #[AsMessageHandler], __invoke(Message) | ✅ | ✅ |
| Dispatch | MessageBusInterface::dispatch() | ✅ | ✅ |
| Transports | contao_prio_low (Doctrine) | TransportNamesStamp(['contao_prio_low']) | ✅ |

### Jobs-Framework

| Aspekt | Contao-Doku | Unsere Umsetzung | Status |
|--------|-------------|------------------|--------|
| Service | contao.job.jobs (Jobs-Klasse) | @?contao.job.jobs | ✅ |
| createJob(type) | Für aktuellen User | createJob('zotero_sync') | ✅ |
| getByUuid(uuid) | Job laden | getByUuid($jobUuid) | ✅ |
| markPending / persist | Vor Verarbeitung | ✅ | ✅ |
| markCompleted / persist | Nach Erfolg | ✅ | ✅ |
| markFailed | **Array** von Fehlern/Keys: `markFailed(['my_error'])` | **String**: markFailed($message) | ⚠️ Anpassung nötig |
| withMetadata | Serialisierbar | withMetadata(['title' => ...]) | ✅ |
| Job already completed | `if (!$job \|\| $job->isCompleted()) return;` | Nicht geprüft | Optional |

### Contao-Beispiel (Jobs-Doku)

```php
$job = $this->jobs->getByUuid($message->getJobId());
if (!$job || $job->isCompleted()) {
    return;
}
$job = $job->markPending();
// ... Verarbeitung mit withProgressFromAmounts ...
$job = $job->markCompleted();
```

---

## 2. Empfohlene Anpassungen

### 2.1 markFailed – Array statt String

**Doku:** `markFailed(['my_error'])` – Array von Fehlern (Translation Keys oder Anzeigetexte).

**Fix:** `markFailed([$message])` – Fehlermeldung als Array übergeben.

### 2.2 Job-bereits-abgeschlossen-Check (optional)

Vor Verarbeitung prüfen: `if (!$job || $job->isCompleted()) { return; }` – verhindert Doppelverarbeitung bei Retries.

### 2.3 Toter Code: runSyncAndRedirect

`runSyncAndRedirect()` wird nie aufgerufen (wir dispatchen immer). Entweder entfernen oder für zukünftigen „Sync-Modus“-Switch behalten. Aktuell: Dead Code.

### 2.4 withProgressFromAmounts (5.7+)

Nicht umgesetzt – würde ZoteroSyncService um Progress-Callback erweitern. Für 5.6 ausreichend; bei Upgrade auf 5.7 sinnvoll.

---

## 3. Referenz: Contao Backend-Search/Reindex

Die Contao-Doku verweist auf `SearchIndexMessage`, `SearchIndexMessageHandler`, `SearchIndexListener` als Beispiel. Diese nutzen:

- Message mit Job-ID
- Handler: getByUuid, markPending, Verarbeitung, markCompleted
- contao.messenger.backend_search.reindex_message_handler (laut debug:container Usages von contao.job.jobs)

---

## 4. Fazit

| Priorität | Anpassung |
|-----------|-----------|
| **Hoch** | markFailed([$message]) statt markFailed($message) |
| Niedrig | Job-isCompleted-Check |
| Optional | runSyncAndRedirect entfernen |
| Später (5.7) | withProgressFromAmounts + Heartbeat |
