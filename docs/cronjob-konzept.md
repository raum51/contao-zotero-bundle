# Cronjob-Konzept für das Zotero-Bundle

Dieses Dokument fasst die offizielle Contao-Dokumentation zu Jobs/Cronjobs zusammen und skizziert die Umsetzung im Rahmen des Zotero-Bundles.

**Quellen:** [Contao Developer Docs – Cron](https://docs.contao.org/dev/framework/cron/), [Contao Handbuch – Cronjob Framework](https://docs.contao.org/5.x/manual/de/performance/cronjobs/), [Contao Developer Docs – Async Messaging](https://docs.contao.org/dev/framework/async-messaging/).

---

## 1. Übersicht: Jobs vs. Cronjobs in Contao

Contao bietet **zwei unterschiedliche Mechanismen** für zeitgesteuerte oder asynchrone Aufgaben:

| Aspekt | **Cronjob Framework** | **Asynchronous Messaging (Symfony Messenger)** |
|--------|------------------------|-------------------------------------------------|
| **Zweck** | Periodisch wiederkehrende Aufgaben (Cleanup, Sync, etc.) | Einmalige, asynchrone Verarbeitung von Nachrichten (z. B. nach Benutzeraktion) |
| **Trigger** | Zeitgesteuert (minutely, hourly, daily, … oder CRON-Expression) | Ereignisgesteuert (Message wird dispatched, Handler verarbeitet) |
| **Registrierung** | `contao.cronjob` Tag / `#[AsCronJob]` | Message + Handler, Messenger-Transport |
| **Ausführung** | `contao:cron` (CLI oder Web-URL) | `messenger:consume` oder WebWorker (kernel.terminate) |
| **Typische Beispiele** | Opt-In-Tokens bereinigen, Registrierungen bereinigen, **Zotero-Sync** | Zip-Datei erstellen, Suchindex-Update pro Seite |

Für den **Zotero-Sync** (periodisch Zotero-Bibliotheken mit lokalen Tabellen abgleichen) ist das **Cronjob Framework** der geeignete Weg. Der Sync ist eine **zeitgesteuerte Aufgabe**, keine reaktive Verarbeitung auf Ereignis.

---

## 2. Contao Cronjob Framework – offizielle Funktionalität

### 2.1 Grundprinzip

- Cronjobs werden als **Services** registriert, getaggt mit `contao.cronjob`.
- Contao führt alle registrierten Cronjobs in den definierten **Intervallen** aus.
- Ausführung erfolgt entweder:
  - **über Web-Besucher** (nach Response, kann Performance beeinträchtigen), oder
  - **über CLI** (`contao:cron`) – **empfohlener Weg**.

### 2.2 Empfohlene Einrichtung (für Betreiber)

**Ein minütlicher System-Cronjob** initiiert das Framework:

```bash
* * * * * <php-binary> <contao-verzeichnis>/vendor/bin/contao-console contao:cron
```

Beispiel (Plesk):

```bash
* * * * * /opt/plesk/php/8.2/bin/php /var/www/vhosts/example.com/vendor/bin/contao-console contao:cron
```

Nach Contao-Standards soll `php bin/console` verwendet werden (nicht `vendor/bin/contao-console`), um das richtige PHP-Binary zu nutzen:

```bash
* * * * * php /pfad/zum/projekt/bin/console contao:cron
```

**Hinweis:** Ab Contao 5.1 erkennt Contao, ob ein echter Cronjob läuft; wenn ja, wird der Frontend-Cron automatisch deaktiviert.

### 2.3 Optionale Konfiguration

| Konfiguration | Beschreibung |
|---------------|--------------|
| `contao.cron.web_listener` | `true` / `false` / `'auto'` (Standard). `false` = Web-Cron komplett deaktivieren. |
| `contao.messenger.workers` | Steuert, ob der Cronjob-Prozess-Manager `messenger:consume`-Worker startet (ab 5.3). |

### 2.4 Registrierung eigener Cronjobs

**Empfohlener Weg:** PHP-Attribut `#[AsCronJob]`:

```php
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;

#[AsCronJob('hourly')]
class ExampleCron
{
    public function __invoke(): void
    {
        // …
    }
}
```

**Alternativen:**
- Service-Annotation (terminal42/service-annotation-bundle): `@CronJob("hourly")`
- Manuelle Tag-Konfiguration in `services.yaml`:
  ```yaml
  App\Cron\ExampleCron:
      tags:
          - { name: contao.cronjob, interval: hourly }
  ```

### 2.5 Intervall-Optionen

| Wert | Beschreibung |
|------|--------------|
| `minutely` | Jede Minute |
| `hourly` | Jede Stunde |
| `daily` | Täglich |
| `weekly` | Wöchentlich |
| `monthly` | Monatlich |
| `yearly` | Jährlich |
| CRON-Expression | z. B. `*/5 * * * *` (alle 5 Minuten) |

### 2.6 Scope: Web vs. CLI

Cronjobs erhalten einen `$scope`-Parameter (`Cron::SCOPE_WEB` oder `Cron::SCOPE_CLI`). Für lang laufende Aufgaben kann der Web-Scope übersprungen werden:

```php
use Contao\CoreBundle\Cron\Cron;
use Contao\CoreBundle\Exception\CronExecutionSkippedException;

public function __invoke(string $scope): void
{
    if (Cron::SCOPE_WEB === $scope) {
        throw new CronExecutionSkippedException();
    }
    // … nur im CLI ausführen
}
```

`CronExecutionSkippedException` bewirkt: Der Job wird als „nicht ausgeführt“ gewertet, die letzte Laufzeit bleibt unverändert – er wird beim nächsten Aufruf erneut angeboten.

### 2.7 Asynchrone Cronjobs (ab 5.1)

Für Jobs, die z. B. Child-Prozesse starten, kann ein `GuzzleHttp\Promise\PromiseInterface` zurückgegeben werden. Contao bietet dafür `ProcessUtil` (z. B. zum Starten eines Symfony-Console-Prozesses). Für den Zotero-Sync reicht in der Regel eine synchrone Ausführung aus.

### 2.8 Testing

- Letzte Ausführung wird in `tl_cron_job` gespeichert.
- Zum erzwungenen erneuten Testen: `php bin/console contao:cron "Vollständiger\Service\Name" --force`
- Alle registrierten Cronjobs anzeigen: `php bin/console debug:container --tag contao.cronjob`

---

## 3. Asynchronous Messaging (Hintergrund)

Das **Symfony Messenger**-Integration in Contao dient **nicht** für periodische Cron-Aufgaben, sondern für:

- Einmalige, asynchrone Verarbeitung (z. B. Zip-Erstellung, E-Mail-Versand)
- Nachrichten, die von einer Aktion ausgelöst und in eine Queue gestellt werden
- WebWorker-Fallback, wenn kein `messenger:consume`-Prozess läuft
- Ab Contao 5.3: Cronjob-Framework startet automatisch `messenger:consume`-Worker (falls minütlicher Cron konfiguriert ist)

**Für den Zotero-Sync** ist Messenger **nicht** erforderlich; der Sync ist eine wiederkehrende Aufgabe, die ideal ins Cronjob Framework passt.

---

## 4. Konzept: Zotero-Sync per Cronjob

### 4.1 Ziel

- **Zotero-Sync** soll periodisch **pro Library** automatisch ausgeführt werden – gesteuert über das bestehende Feld `sync_interval` in `tl_zotero_library`.
- Nutzer, die das [Contao Cronjob Framework](https://docs.contao.org/5.x/manual/de/performance/cronjobs/) einrichten, erhalten den Sync ohne weitere Konfiguration.
- Der manuelle Sync (Backend-Button, CLI-Command) bleibt unverändert.

### 4.2 Bestehendes Feld: `sync_interval` (tl_zotero_library)

Das Feld **`sync_interval`** existiert bereits in `tl_zotero_library`:

| Aspekt | Aktuell | Geplant |
|--------|---------|---------|
| **Beschriftung (DE)** | „Sync-Intervall (Sekunden)“ | „Sync-Intervall (Stunden)“ |
| **Beschriftung (EN)** | „Sync interval (seconds)“ | „Sync interval (hours)“ |
| **Einheit** | Sekunden (praktisch unpraktikabel: 3600 für 1h) | **Stunden** (1 = 1 h, 24 = 1 Tag) |
| **Bedeutung** | in Stunden (1 = 1 h, 24 = 1 Tag) – 0 = nur manuell | Unverändert |
| **Datentyp** | `int unsigned` | Unverändert |

**Gründe für Stunden:**
- Sekunden und Minuten sind für einen Zotero-Sync unnötig fein.
- Stunden ermöglichen sinnvolle Werte: 1, 6, 12, 24 – passend für typische Sync-Bedürfnisse.
- Der ZoteroSyncCron kann `hourly` laufen (statt `minutely`) – deutlich weniger Cron-Ausführungen pro Tag.

### 4.3 Architektur

```
┌─────────────────────────────────────────────────────────────────┐
│  System-Cron (minütlich)                                         │
│  php bin/console contao:cron                                     │
└─────────────────────────────┬───────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  Contao Cron-Service                                             │
│  Führt alle Jobs mit Tag contao.cronjob in definierten           │
│  Intervallen aus (tl_cron_job speichert letzte Laufzeit)        │
└─────────────────────────────┬───────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  ZoteroSyncCron (#[AsCronJob('hourly')])                         │
│  - Prüft Scope: Nur CLI (CronExecutionSkippedException bei Web) │
│  - Lädt alle published Libraries mit sync_interval > 0          │
│  - Pro Library: Prüft last_sync_at + (sync_interval * 3600) ≤ now│
│  - Nur fällige Libraries: ZoteroLocaleService + Sync (pro ID)   │
└─────────────────────────────────────────────────────────────────┘
```

### 4.4 Implementierung

#### 4.4.1 Neue Klasse: `ZoteroSyncCron`

**Pfad:** `bundles/raum51/contao-zotero-bundle/src/Cron/ZoteroSyncCron.php`

| Aspekt | Entscheidung |
|--------|--------------|
| **Intervall** | `hourly` – Der Cron läuft stündlich und prüft, welche Libraries das jeweilige `sync_interval` (in Stunden) überschritten haben. Weniger Cron-Läufe als bei minutely. |
| **Scope** | Nur CLI. Im Web-Scope `CronExecutionSkippedException`, da Sync mehrere Sekunden dauern kann. |
| **Logik** | 1) Alle published Libraries mit `sync_interval > 0` laden. 2) Pro Library: `last_sync_at + (sync_interval * 3600)` ≤ `time()`? 3) Nur fällige Libraries synchronisieren (einzeln oder gebündelt, s. u.). |
| **Aufruf** | Für jede fällige Library: `ZoteroLocaleService::fetchAndStore()` einmal pro Lauf; `ZoteroSyncService::sync($libraryId, true)` pro Library. |
| **Logging** | Kanal `contao.cron` – Einträge im Contao-Systemlog (Backend > System > Protokoll). |
| **Fehlerbehandlung** | Fehler werden vom SyncService geloggt; der Cron wirft keine Exception. |

**Sonderfall `last_sync_at = 0`:** Bei noch nie synchronisierten Libraries (z. B. nach Ersteinrichtung) gilt: sofort als fällig; der erste Cron-Lauf führt den Sync aus.

#### 4.4.2 Registrierung

```php
#[AsCronJob('hourly')]
class ZoteroSyncCron { … }
```

oder in `services.yaml`:

```yaml
Raum51\ContaoZoteroBundle\Cron\ZoteroSyncCron:
    tags:
        - { name: contao.cronjob, interval: hourly }
```

#### 4.4.3 Abhängigkeiten

- `ZoteroSyncService`
- `ZoteroLocaleService`
- `Doctrine\DBAL\Connection` (oder Repository) zum Laden der Libraries mit `sync_interval > 0`, `published = 1`
- `Psr\Log\LoggerInterface` (monolog.logger.contao.cron)

### 4.5 Verhalten im Detail

1. **Welche Libraries werden synchronisiert?**  
   Nur **published** Libraries mit **`sync_interval > 0`**, bei denen das Intervall abgelaufen ist (`last_sync_at + sync_interval Stunden ≤ now`).

2. **`sync_interval = 0`:**  
   Kein automatischer Sync – nur manuell (Backend-Button oder CLI).

3. **Beispielwerte (Stunden):**  
   - 1 = stündlich  
   - 6 = alle 6 Stunden  
   - 24 = täglich  

4. **Locales:**  
   Einmal pro Cron-Lauf `ZoteroLocaleService::fetchAndStore()` (nur wenn mindestens eine Library fällig ist – optional als Optimierung).

5. **Reihenfolge:**  
   Libraries nacheinander synchronisieren, um API-Limits und Last zu berücksichtigen.

### 4.6 DCA- und Sprachdatei-Anpassungen

| Datei | Änderung |
|-------|----------|
| `tl_zotero_library` (DE) | `sync_interval` → „Sync-Intervall (Stunden)“; „in Stunden (1 = 1 h, 24 = 1 Tag) – 0 = nur manuell“ |
| `tl_zotero_library` (EN) | `sync_interval` → „Sync interval (hours)“; „in hours (1 = 1 h, 24 = 1 day) – 0 = manual only“ |
| DCA | Keine Strukturänderung – Feld und SQL bleiben; nur Label-Referenz nutzt neue Übersetzung |

### 4.7 Nutzer-Dokumentation

In der Bundle-**README.md** bzw. in einer separaten Anleitung:

1. **Voraussetzung:** Contao Cronjob Framework (minütlicher System-Cron: `php bin/console contao:cron`).
2. **Pro-Library-Konfiguration:** In jeder Library „Sync-Intervall (Stunden)“ setzen (1 = 1 h, 24 = 1 Tag; 0 = nur manuell).
3. **Manueller Sync:** Unverändert über Backend-Button oder `php bin/console contao:zotero:sync`.
4. **DDEV:** Hinweis auf [DDEV Cronjob-Setup](https://docs.contao.org/5.x/manual/de/anleitungen/lokale-installation/ddev/).

### 4.8 Asynchroner Sync per Backend-Button (Timeout-Vermeidung)

**Problem:** Wird der Sync über die Backend-Buttons ausgelöst, würde er synchron im HTTP-Request laufen. Bei großen Bibliotheken führt das zu Timeouts.

**Lösung (ab Contao 5.6):** Der Sync läuft **asynchron** über **Symfony Messenger + Contao Job-Framework**. Die Buttons dispatchen eine `ZoteroSyncMessage`; der `ZoteroSyncMessageHandler` verarbeitet sie im Hintergrund (WebWorker oder messenger:consume). Der User erhält sofort „Sync gestartet“, Fortschritt ist unter Backend > Jobs sichtbar.

**Fallback (Contao 5.3–5.5):** Kein Job-Framework → synchroner Sync im Request (wie früher).

#### 4.8.1 Backend-Trigger-Stellen

Der Sync kann an **vier Stellen** im Backend ausgelöst werden:

| Trigger (DCA-Key) | Kontext | Message-Parameter |
|-------------------|---------|-------------------|
| `zotero_sync` | Eine Library (ID aus Request) | libraryId, resetFirst=false |
| `zotero_reset_sync` | Eine Library, Reset vor Sync | libraryId, resetFirst=true |
| `zotero_sync_all` | Alle publizierten Libraries | libraryId=null, resetFirst=false |
| `zotero_reset_sync_all` | Alle publizierten, Reset vor Sync | libraryId=null, resetFirst=true |

**Implementierungsort:** `ZoteroLibrarySyncCallback` (config.onload für tl_zotero_library).

#### 4.8.2 Technische Umsetzung

1. **Message dispatch** – `ZoteroSyncMessage` mit `TransportNamesStamp(['contao_prio_low'])` für zuverlässiges Routing auf den Doctrine-Transport.
2. **Job (5.6+)** – Bei verfügbarem Jobs-Service: Job erstellen, UUID an Message übergeben; Handler setzt markPending/markCompleted/markFailed. Backend-Overlay zeigt Fortschritt.
3. **Sofortige Response** – Message „Sync gestartet. Fortschritt im Backend sichtbar.“, Redirect zur passenden Seite.

#### 4.8.3 Vorteile

- Kein Timeout im HTTP-Request
- Fortschritt unter Backend > Jobs sichtbar (5.6+)
- Contao 5.7: Fortschrittsbalken und Attachments (Sync-Report, Fehler-Datei)
- Kein Prozess-Spawn – nutzt Contao-Standard (Messenger, WebWorker)

#### 4.8.4 Einschränkungen

- Erfolgsdetails (Items erstellt/aktualisiert) nur in 5.7 als Job-Attachment oder in Logs
- Fehlgeschlagene Messages: `messenger:failed:remove`; Jobs-Tabelle: kein Backend-Purge (manuell per SQL)

---

## 5. Offene Punkte / Optionen

| Thema | Empfehlung |
|-------|------------|
| **Select mit Vorgabewerten** | Optional: Statt Freitext ein Select mit Optionen (0, 1, 6, 12, 24 Stunden) für bessere UX. |
| **Locales nur bei fälligem Sync** | Optional: `fetchAndStore()` nur ausführen, wenn mindestens eine Library synchronisiert wird. |
| **Attachments / download_attachments** | Unverändert – der Sync holt Metadaten; Attachment-Downloads werden separat (Blueprint) behandelt. |

---

## 6. Zusammenfassung

| Aspekt | Inhalt |
|--------|--------|
| **Contao-Standard** | Cronjob Framework mit `contao.cronjob` Tag / `#[AsCronJob]` |
| **Empfohlene Einrichtung** | Minütlicher System-Cron: `php bin/console contao:cron` |
| **Zotero-Bundle** | Neuer `ZoteroSyncCron` mit `#[AsCronJob('hourly')]`, Scope nur CLI |
| **Steuerung** | Pro-Library über `sync_interval` (in Stunden: 1 = 1 h, 24 = 1 Tag; 0 = nur manuell) in `tl_zotero_library` |
| **Backend-Buttons** | Async (5.6+): Alle vier Sync-Trigger dispatchen ZoteroSyncMessage via Messenger; Handler im Hintergrund, Job-Overlay zeigt Fortschritt. Fallback 5.3–5.5: synchron. |
| **DCA-Anpassung** | Label: „Sekunden“ → „Stunden“ in DE/EN |
| **Abhängigkeiten** | ZoteroSyncService, ZoteroLocaleService, Connection/Repository, Logger |
| **Dokumentation** | README-Erweiterung mit Cronjob-Anleitung |

---

*Erstellt am 18.02.2026. Quellen: [Contao Dev – Cron](https://docs.contao.org/dev/framework/cron/), [Contao User – Cronjob Framework](https://docs.contao.org/5.x/manual/de/performance/cronjobs/), [Contao Dev – Async Messaging](https://docs.contao.org/dev/framework/async-messaging/).*
