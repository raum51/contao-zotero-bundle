# Job-Framework: Verfügbarkeit pro Contao-Version

Stand: 2026-02-18

## Übersicht

| Funktionalität | Contao 5.3 LTS | Contao 5.6 | Contao 5.7 |
|----------------|:--------------:|:----------:|:----------:|
| Jobs-Service (`Contao\CoreBundle\Job\Jobs`) | ❌ | ✅ | ✅ |
| Job-DTO (create, persist, markPending/Completed/Failed) | ❌ | ✅ | ✅ |
| Progress (`withProgress`, `withProgressFromAmounts`) | ❌ | ⚠️ Teilweise | ✅ Vollständig |
| Attachments (`addAttachment`) | ❌ | ❌ | ✅ |
| Progress-Balken im Backend | ❌ | ❌ | ✅ |
| Message-Bus-Integration (bessere DX) | ❌ | ❌ | ✅ |
| Fortschritts-Anzeige im Backend | ❌ | ❌ | ✅ |
| `markFailedBecauseRequiresCLI` | ❌ | ✅ | ✅ |
| Backend-Overlay, Polling | ❌ | ✅ | ✅ |
| Status-Enum (new, pending, completed, failed) | ❌ | ✅ | ✅ |
| `findMyNewOrPending`, `getByUuid` | ❌ | ✅ | ✅ |
| `createJob`, `createSystemJob`, `createUserJob` | ❌ | ✅ | ✅ |

---

## Detail: Einführung der Features

### Contao 5.3 LTS (ab 5.3.0, Februar 2024 – bis 5.7 nächste LTS)

**Kein Job-Framework.** Das Job-Framework wurde erst in Contao 5.6 eingeführt.

**Verfügbare Alternativen in 5.3:**

| Baustein | Verfügbarkeit | Hinweis |
|----------|---------------|---------|
| **Cron-Framework** | 5.1+ | `#[AsCronJob('hourly')]`, `contao.cronjob`-Tag |
| **ProcessUtil** (Cron-Kontext) | 5.1+ | `createSymfonyConsoleProcess()`, `createPromise()` – **nur im Cron**, nicht für Backend-Trigger dokumentiert |
| **Async Messaging (Symfony Messenger)** | 5.3.10+ | Doctrine-Transport, `contao_prio_*`, WebWorker (kernel.terminate) |
| **contao.cron.supervise_workers** | 5.3+ | Startet Worker-Prozesse für Messenger |

**Implikation für Zotero-Bundle:** In 5.3 steht nur **synchroner Backend-Sync** oder **reines Message-Dispatch** (ohne Job-Integration, ohne Fortschritts-Anzeige) zur Verfügung. Alternativ: Sync ausschließlich über CLI/Cron.

---

### Contao 5.4 und 5.5

Gleiche Situation wie 5.3 – kein Job-Framework. Cron, ProcessUtil (Cron), Messenger verfügbar. Job-Framework kommt erst mit 5.6.

---

### Contao 5.6 (ab 5.6.0, August 2025)

**PR #8066** „Add the foundation for jobs“ (Merged: 2025-07-08)

- **Grundlage:** Jobs-Service, Job-DTO, persist(), Backend-Overlay, Polling
- **Status:** new, pending, completed, failed
- **Methoden:** markPending, markCompleted, markFailed, withProgress, withMetadata
- **Explizit später geplant** (laut PR-Beschreibung):
  - Display error messages if the job fails
  - Download attachments (e.g. for a zip file download)
  - Display a progress bar if needed

Weitere 5.6-PRs:
- #8700 – A completed job must always be set to 100% progress
- #8766 – Make a job optional for the back end search reindex
- #8824 – Reduce the jobs polling interval to 5 seconds
- #8966 – Increase the z-index of the jobs overlay

### Contao 5.7 (ab 5.7.0, Februar 2026)

**Neue Job-Framework-Features:**

| PR | Beschreibung |
|----|--------------|
| [#8818](https://github.com/contao/contao/pull/8818) | Implement attachments for the jobs framework |
| [#8849](https://github.com/contao/contao/pull/8849) | Add a progress bar to the jobs framework |
| [#8825](https://github.com/contao/contao/pull/8825) | Add a helper method for the job progress based on amounts (`withProgressFromAmounts`) |
| [#8830](https://github.com/contao/contao/pull/8830) | Add job status helpers |
| [#9016](https://github.com/contao/contao/pull/9016) | Support downloading multiple job attachments |
| [#9083](https://github.com/contao/contao/pull/9083) | Integrate the message bus in the jobs framework for better DX |
| [#9095](https://github.com/contao/contao/pull/9095) | Style the jobs widget nicely |
| [#9150](https://github.com/contao/contao/pull/9150) | Dynamically update the job view |
| [#8826](https://github.com/contao/contao/pull/8826) | Migrate the legacy crawl logic to the new jobs framework |
| [#9013](https://github.com/contao/contao/pull/9013) | Add progress for the back end search jobs |

---

## Doku-Diskrepanz

- **Contao-Doku** ([docs.contao.org/5.x/dev/framework/jobs/](https://docs.contao.org/5.x/dev/framework/jobs/)): „This feature is available in **Contao 5.7** and later.“
- **trakked.io** ([Changelog 5.7](https://www.trakked.io/de/blog/was-du-ueber-contao-5-7-lts-wissen-musst)): „Das bereits in **Contao 5.6** eingeführte Job-Framework wurde kräftig ausgebaut.“

**Interpretation:** Das Job-Framework wurde in **5.6** eingeführt (PR #8066, Milestone 5.6). Die offizielle Doku dokumentiert vermutlich den 5.7-Branch und nennt daher „5.7“. Die **Grundfunktionen** (Jobs-Service, persist, Status, withProgress) sind in 5.6 vorhanden; **Attachments, Fortschrittsbalken, withProgressFromAmounts, Message-Bus-Integration** sind 5.7-spezifisch.

---

## Empfehlung für Zotero-Bundle (Schritt 2)

### Benötigte Funktionen für Zotero-Sync via Job-Framework

1. **Jobs-Service** – Job erstellen, persistieren, Status setzen
2. **Fortschritt** – `withProgressFromAmounts` oder `withProgress` für Langläufer-Feedback
3. **Messenger-Integration** – Message dispatch aus dem Backend, Handler führt Sync aus
4. **Attachments (optional)** – Log-Dateien/Fehlerberichte an Job hängen

### Fallback-Strategie für Contao 5.3 / 5.6 / 5.7

| Ansatz | Contao 5.3 LTS | Contao 5.6 | Contao 5.7+ |
|--------|----------------|------------|-------------|
| **Versionsprüfung** | `class_exists(\Contao\CoreBundle\Job\Jobs::class)` – fehlt in 5.3/5.4/5.5 |
| **5.3/5.4/5.5** | Synchroner Sync ODER Message-Dispatch (ohne Job, ohne Fortschritt). CLI/Cron empfohlen. | – | – |
| **5.6-Fallback** | – | Synchroner Sync oder Job ohne Fortschrittsbalken/Attachments | – |
| **5.7+** | – | – | Job-Framework mit Fortschritt, Attachments, Backend-Widget |

### Technische Prüfung zur Laufzeit

```php
// Job-Framework verfügbar? (ab 5.6)
$jobsAvailable = class_exists(\Contao\CoreBundle\Job\Jobs::class);

// withProgressFromAmounts verfügbar? (ab 5.7)
$progressFromAmountsAvailable = $jobsAvailable
    && method_exists(\Contao\CoreBundle\Job\Job::class, 'withProgressFromAmounts');

// addAttachment verfügbar? (ab 5.7)
$attachmentsAvailable = $jobsAvailable
    && method_exists(\Contao\CoreBundle\Job\Jobs::class, 'addAttachment');
```

### Minimaler Zotero-Sync ohne 5.7-Features (5.6-Fallback)

In Contao 5.6 könnte man:
- Einen Job erstellen und persistieren
- `withProgress($percent)` nutzen (ohne `withProgressFromAmounts`)
- Keine Attachments
- Keinen Fortschrittsbalken im Backend (nur Status-Überblick)

Ob das sinnvoll ist, hängt davon ab, ob der synchroner Sync in 5.6 ohnehin ausreicht oder ob die Nutzer den Job-Status sehen wollen.

---

## Quellen

- [Contao Jobs Framework (Docs)](https://docs.contao.org/5.x/dev/framework/jobs/)
- [PR #8066 – Add the foundation for jobs](https://github.com/contao/contao/pull/8066)
- [Contao 5.3 Changelog (trakked.io)](https://www.trakked.io/en/contao-open-source-cms-changelog-for-the-version-5-3)
- [Contao 5.6 Changelog (trakked.io)](https://www.trakked.io/en/contao-open-source-cms-changelog-for-the-version-5-6)
- [Contao 5.7 Changelog (trakked.io)](https://www.trakked.io/en/contao-open-source-cms-changelog-for-the-version-5-7)
- [Contao Cron Framework (incl. ProcessUtil)](https://docs.contao.org/dev/framework/cron/)
- [Contao Async Messaging](https://docs.contao.org/dev/framework/async-messaging/)
- [recherche-backend-sync-alternativen.md](./recherche-backend-sync-alternativen.md)
