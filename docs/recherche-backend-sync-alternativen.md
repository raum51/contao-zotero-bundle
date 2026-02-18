# Recherche: Backend-Sync-Alternativen – Shell-Befehle & Job-Framework

Stand: 2026-02-18

## 1. Shell-Kommandos aus dem Contao-Backend

### Recherche-Ergebnis: Kein dedizierter „Backend-Trigger für Shell“ in ContaoDev

In der [Contao Developer Documentation](https://docs.contao.org/dev/) gibt es **keine explizite Doku** für „Shell-Kommandos aus dem Backend absetzen“. Contao sieht dafür primär vor:

- **CLI-Commands** über `php bin/console <command>` – manuell oder per Cron
- **Cronjob-Framework** – zeitgesteuerte Ausführung von Jobs
- **Asynchrones Messaging** – Messages als Hintergrundaufgaben

### Relevante Bausteine

| Baustein | Ort | Verwendung |
|----------|-----|------------|
| **ProcessUtil** | `Contao\CoreBundle\Util\ProcessUtil` | `createSymfonyConsoleProcess('command', 'arg1')` – findet PHP-Binary, erstellt Process. **Nur im Cron-Kontext dokumentiert** (asynchrone Cronjobs). |
| **kernel.terminate** | Symfony HttpKernel | Läuft nach Absenden der Response. WebWorker nutzt dies für Message-Verarbeitung. Laut [Stack Overflow](https://stackoverflow.com/questions/69195529): „Process is not meant to run after the parent dies“ – Response senden kann Kind-Prozesse beenden. |
| **Cron + ProcessUtil** | [Contao Dev – Cron](https://docs.contao.org/dev/framework/cron/) | Cronjobs können `GuzzleHttp\Promise\PromiseInterface` zurückgeben; `ProcessUtil::createPromise(Process)` für asynchrone Prozesse. Gilt für **CLI-Scope** (contao:cron), nicht für Web-Request. |

### Schlussfolgerung Shell-Backend-Sync

- **Process/exec aus dem Web-Request** ist problematisch: kein TTY, evtl. falsches PHP, Kind-Prozesse können beim Response-Senden beendet werden.
- **ProcessUtil** ist für **Cron** konzipiert, nicht für Backend-Trigger aus dem Web.
- **Empfehlung:** Sync nicht per Shell-Spawn aus dem Backend starten, sondern über **Message/Job-Queue** anstoßen.

---

## 2. Contao Job-Framework (5.7)

### Verfügbarkeit

- **Contao 5.7+**
- Status: **experimentell** – nicht BC-garantiert, Klassen mit `@experimental`
- Doku: [Jobs Framework](https://docs.contao.org/5.x/dev/framework/jobs/)

### Kernkonzepte

- **Jobs** = immutable DTOs mit UUID, Typ, Status, Owner, Timestamp
- **Jobs-Service** (`Contao\CoreBundle\Job\Jobs`) – API zum Erzeugen, Abrufen und Persistieren
- Status: `new`, `pending`, `completed`, `failed`
- **Fortschritt:** 0–100 %, inkl. `withProgressFromAmounts($done, $total)` – bei unbekanntem `$total` (null) logarithmische Kurve, Obergrenze 95 %
- **Attachments** – z. B. Export-Dateien
- **Backend-Integration** – Contao kümmert sich um Anzeige, Live-Updates usw.

### Integration mit Asynchronem Messaging

Jobs werden **über Messenger-Messages** abgearbeitet. Beispiel aus der Doku:

```php
#[AsMessageHandler]
class MyMessageHandler
{
    public function __invoke(MyMessage $message): void
    {
        $job = $this->jobs->getByUuid($message->getJobId());
        // ...
        $job = $job->markPending();
        $this->jobs->persist($job);

        foreach ($this->connection->fetchAllAssociative('SELECT * FROM foo') as $i => $item) {
            // Do heavy work
            $job = $job->withProgressFromAmounts($i + 1);
            $this->jobs->persist($job);
        }

        $job = $job->markCompleted();
        $this->jobs->persist($job);
    }
}
```

---

## 3. Asynchrones Messaging – Langläufer

### Standard-Limits

- **Cron-Worker:** `--time-limit=60` (60 Sekunden pro Worker-Lauf)
- **WebWorker (kernel.terminate):** begrenzt durch `max_execution_time`
- Wenn ein Handler länger als das Limit läuft → Prozess wird beendet, Message evtl. nicht vollständig verarbeitet

### Strategien für Langläufer

| Strategie | Beschreibung |
|-----------|--------------|
| **Chunking** | Großen Sync in mehrere Messages aufteilen (z. B. pro Library, pro Batch von Items). Jede Message macht einen kleinen Teil. |
| **Progress + Persist** | Regelmäßig `$job->withProgressFromAmounts(...)` + `persist()` – Status bleibt erhalten, auch wenn Worker neu startet. |
| **Time-Limit pro Transport** | Eigener Transport mit höherem `--time-limit` konfigurieren (falls Cron-Worker angepasst werden). |
| **markFailedBecauseRequiresCLI** | Job explizit als „braucht CLI“ markieren, wenn keine Queue-Verarbeitung gewünscht ist. |

### WebWorker & Grace Period

- **Grace Period** (Standard 10 Min): Zeit, in der Contao annimmt, ein „echter“ Worker läuft. Wenn in dieser Zeit kein `WorkerRunningEvent` kommt (weil eine Message sehr lange dauert), schaltet der WebWorker ein.
- **Langläufer:** Wenn eine Message z. B. 15 Min braucht, wird nach der Grace Period der WebWorker aktiv – idealerweise hat der erste Worker die Message bis dahin aber schon abgeschlossen oder es gibt Chunking.

---

## 4. Heartbeat

### Recherche

- **WorkerRunningEvent:** Wird in der Worker-Schleife gefeuert, eher bei **Idle**-Phasen, nicht während der Handler-Ausführung.
- **AMQP Heartbeat:** Relevant für Queue-Provider wie RabbitMQ, um Verbindung am Leben zu halten – nicht für Doctrine-Transports.
- **Contao Jobs:** Kein expliziter „Heartbeat“, aber **Fortschritts-Updates** (`withProgressFromAmounts` + `persist`) wirken ähnlich: zeigen, dass der Job noch aktiv ist.

### Pragmatischer Ansatz für Zotero-Sync

- Regelmäßig `$job->withProgressFromAmounts($processed, $total)` + `$this->jobs->persist($job)` aufrufen.
- Das aktualisiert den Job in der DB und signalisiert „läuft noch“.
- Kein eigener Heartbeat-Mechanismus nötig, wenn Fortschritt ohnehin gepflegt wird.

---

## 5. Empfohlener Weg für Zotero-Backend-Sync

### Option A: Messenger + Jobs (Contao 5.7+)

1. **Message** mit Library-ID und ggf. Job-UUID erstellen.
2. Message in eine Queue (z. B. `contao_prio_low`) dispatchen.
3. **Handler** lädt Job per UUID, führt Sync aus, aktualisiert Fortschritt und Status.
4. **Chunking:** Bei großem Sync – z. B. Message pro Library oder pro Items-Batch – um Time-Limits zu vermeiden.

### Option B: Weiterhin Cron + manueller CLI-Hinweis

- Sync ausschließlich über `contao:zotero:sync` (Cron oder manuell).
- Im Backend nur Hinweis: „Sync per Cron oder `php bin/console contao:zotero:sync` starten“.
- Kein Versuch, aus dem Web-Request heraus einen Prozess zu spawnen.

---

## 6. Quellen

### Offizielle Contao-Dokumentation

| Thema | URL |
|-------|-----|
| Jobs Framework | [docs.contao.org/5.x/dev/framework/jobs/](https://docs.contao.org/5.x/dev/framework/jobs/) |
| Asynchrones Messaging | [docs.contao.org/dev/framework/async-messaging](https://docs.contao.org/dev/framework/async-messaging) |
| Cron (inkl. ProcessUtil) | [docs.contao.org/dev/framework/cron](https://docs.contao.org/dev/framework/cron) |
| Symfony Process + API-Request | [Stack Overflow: async process](https://stackoverflow.com/questions/69195529) |

### Vertrauenswürdige Contao-Recherche-Quellen (Projektstandard)

| Quelle | URL | Beschreibung |
|--------|-----|--------------|
| pdir Agentur- & Webdesign-Blog | [pdir.de/agentur-webdesign-blog.html](https://pdir.de/agentur-webdesign-blog.html) | Contao-News, Tutorials, Erweiterungen (z. B. [Contao 5.7 LTS](https://pdir.de/news/contao-5-7.html)) |
| trakked.io Blog | [trakked.io/de/unser-blog](https://www.trakked.io/de/unser-blog) | Contao-Updates, Changelog, Tipps & Tricks |
| Contao News (offiziell) | [contao.org/de/news](https://contao.org/de/news) | Offizielle Ankündigungen, Release-Infos, Community |

---

## 8. Ergänzung: Recherche in pdir, trakked.io, contao.org (2026-02-18)

### contao.org – Offizielle Ankündigung Contao 5.7 LTS

[Contao 5.7 LTS - unbegrenzte Möglichkeiten mit begrenzter Breite](https://contao.org/de/news/contao-5-7-lts-unbegrenzte-moeglichkeiten-mit-begrenzter-breite)

**Abschnitt „Das Jobs-Framework“ (Auszug):**

- „Letztes Jahr wurde das fantastische Job-Framework implementiert, welches lange oder langweilige Aufgaben im Hintergrund ermöglicht.“
- **Neu in 5.7:** Statusleiste + Dateianhänge („kann außerdem Dateianhänge ausgeben“)
- Suchindex-Aktualisierung als Beispiel: „Wenn du das nächste Mal den Suchindex aktualisierst, kannst du dich von den neuen Funktionen überzeugen.“
- Aufforderung an Entwickler: „Nutzt du in deinen Erweiterungen oder Projekten auch das Job-Framework? Schreib es doch mal in die Kommentare.“
- Relevante GitHub-PRs: u. a. [#8830](https://github.com/contao/contao/pull/8830), [#8818](https://github.com/contao/contao/pull/8818), [#8849](https://github.com/contao/contao/pull/8849), [#9016](https://github.com/contao/contao/pull/9016), [#9083](https://github.com/contao/contao/pull/9083), [#9095](https://github.com/contao/contao/pull/9095), [#9150](https://github.com/contao/contao/pull/9150), [#9013](https://github.com/contao/contao/pull/9013), [#8826](https://github.com/contao/contao/pull/8826)

### trakked.io – „Was du über Contao 5.7 LTS wissen musst“

[Was du über Contao 5.7 LTS wissen musst](https://www.trakked.io/de/blog/was-du-ueber-contao-5-7-lts-wissen-musst)

**Abschnitt „Job-Framework: Hintergrundprozesse im Blick“:**

- „Das bereits in Contao 5.6 eingeführte Job-Framework wurde kräftig ausgebaut.“
- **Fortschritt sichtbar:** „Der Fortschritt laufender Prozesse ist jetzt überall im Backend und auf der Job-Liste direkt sichtbar.“
- **Attachments:** Entwickler können Anhänge wie Logs oder Reports zu Jobs hinzufügen.
- **Suchindex:** „Die bisherige Crawler-Implementierung zur Indexierung des Suchindexes wurde vollständig auf das Job-Framework umgebaut.“
- **Asynchrone Aufgaben:** „Besonders für die Entwicklung bietet das Job-Framework nun die Möglichkeit, eigene Aufgaben asynchron abarbeiten zu lassen. Die Basis ist vorhanden und bringt Contao völlig neue Möglichkeiten.“
- **Cronjob-Supervisor:** Fallback per `flock()` für Hoster ohne `ps` (z. B. All-Inkl) – ermöglicht Job-Framework und Crons auch auf „bisher inkompatiblen Hostern“.

### pdir.de – „Contao 5.7 LTS: Alle Neuerungen im Überblick“

[Contao 5.7 LTS (pdir)](https://pdir.de/news/contao-5-7.html)

**Relevante Stellen:**

- **Hintergrundjobs:** „Hintergrundjobs für die Suche zeigen ihren Fortschritt an, sodass du siehst, ob die Indexierung oder ein größerer Suchlauf noch läuft.“
- **Langläufer:** „Exporte oder andere langlaufende Prozesse“ – „das Durchsuchen großer Websites“, „der Aufbau von Suchindizes“
- **Backend-Widget:** „Fortschrittsbalken und Statusanzeigen machen sichtbar, wie weit ein Job ist.“ – „zentrales ‚Cockpit‘ für laufende Aufgaben“
- **Fazit:** „das erweiterte Jobs‑Framework“ als einer der zentralen Punkte von 5.7

### Relevanz für Zotero-Sync

| Aspekt | Erkenntnis |
|--------|------------|
| Langläufer | pdir und contao.org bestätigen explizit: „lange oder langweilige Aufgaben“, „Exporte oder andere langlaufende Prozesse“ – Job-Framework ist für Langläufer vorgesehen |
| Fortschritt | Fortschrittsbalken und Status sind in 5.7 eingebaut; keine zusätzliche Heartbeat-Logik nötig, wenn Progress-Updates genutzt werden |
| Attachments | Logs/Reports können an Jobs gehängt werden – für Sync-Details oder Fehlerberichte geeignet |
| Suchindex als Referenz | Contao hat den Crawler vollständig auf Jobs umgestellt – gutes Referenzmuster für komplexe Hintergrundaufgaben |

---

## 7. TODO-Status

- [ ] Auf Job-Framework von Contao 5.7 umstellen (bereits in `todo.md`)
- [ ] Chunking-Strategie für Zotero-Sync definieren (Library-Granularität vs. Item-Batches)
- [ ] Prozess-Spawn aus Backend einstellen bzw. durch Message-Dispatch ersetzen
