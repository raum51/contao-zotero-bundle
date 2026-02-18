# Recherche: Backend-Hinweise in Contao

Stand: 2025-02-18

## Ziel

Übersicht über die Möglichkeiten in Contao, Backend-Benutzern Hinweise anzuzeigen (z. B. bei Sync-Fehlern), insbesondere:
- Wo erscheinen Hinweise?
- Welche Mechanismen gibt es?
- Welche offizielle Doku existiert?

---

## 1. getSystemMessages (Backend-Startseite / Dashboard)

### Beschreibung

Hook `getSystemMessages` – fügt Meldungen auf der **Backend-Startseite** (Dashboard) hinzu. Sichtbar, wenn ein Nutzer nach dem Login dort landet.

### Ort

- **Wo:** Inhalt der Backend-Startseite (nicht in der Header-Glocke)
- **Wann:** Beim Rendern der Dashboard-Seite

### Offizielle Dokumentation

- [Contao Developer – getSystemMessages](https://docs.contao.org/dev/reference/hooks/getSystemMessages)
- Keine Parameter, Rückgabewert: String (HTML erlaubt) oder leerer String

### Beispiel (aus Doku)

```php
#[AsHook('getSystemMessages')]
class GetSystemMessagesListener
{
    public function __invoke(): string
    {
        if (empty($GLOBALS['TL_ADMIN_EMAIL'])) {
            return '<p class="tl_error">Please add your email address to system settings.</p>';
        }
        return '';
    }
}
```

### Verwendung im Zotero-Bundle

- **GetSystemMessagesSyncWarningListener** – zeigt rote Meldung bei Libraries mit `last_sync_status != OK`
- Nutzt `tl_error` für Fehlermeldungen

---

## 2. System-Log (tl_log) & ContaoContext

### Beschreibung

Contao nutzt `ContaoContext`, um Log-Einträge ins **System-Log** zu schreiben. Das System-Log ist unter **System > Systemwartung** erreichbar.

### Ort

- **Wo:** System-Log (tl_log) – Menüpunkt im Backend
- **Glocke im Header:** Unklar, ob die Glocke direkt auf das System-Log verweist oder eine Badge mit Fehleranzahl anzeigt; in der offiziellen Doku nicht eindeutig dokumentiert

### Offizielle Dokumentation

- [Contao Developer – Logging](https://docs.contao.org/dev/framework/logging/)
- [Contao Developer – Log Contexts & Contao's System Log](https://docs.contao.org/dev/framework/logging/#contao-channels-convenience-loggers)

### Kategorien (ContaoContext-Actions)

- `TL_ERROR`
- `TL_ACCESS`
- `TL_GENERAL`
- `TL_FILES`
- `TL_CRON`
- `TL_FORMS`
- `TL_CONFIGURATION`
- `TL_NEWSLETTER`
- `TL_REPOSITORY`

### Beispiel

```php
use Contao\CoreBundle\Monolog\ContaoContext;

$logger->error(
    'Zotero-Sync fehlgeschlagen für Library XYZ',
    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
);
```

### Convenience: contao.error-Kanal

Logger mit Kanal `contao.error` erhalten automatisch `ContaoContext::ERROR`; Einträge erscheinen im System-Log.

---

## 3. badge_title (config)

### Beschreibung

Konfigurations-Parameter `contao.backend.badge_title` – beeinflusst den **Titel** des Backend-Badges (z. B. „Contao“, „develop“).

### Relevant für Backend-Hinweise?

- **Nein** – betrifft nur den Badge-Titel, nicht Benachrichtigungsinhalte oder die Glocke.

### Quelle

- [Contao Manual – Einstellungen](https://docs.contao.org/5.x/manual/de/system/einstellungen/)

---

## 4. Glocke im Backend-Header

### Offene Fragen

- Zeigt die Glocke Einträge aus dem System-Log (tl_log)?
- Zeigt sie einen Badge mit der Anzahl neuer/ungelesener Fehler?
- Gibt es eine offizielle Doku zur Glocke?
- Verknüpfung zwischen `getSystemMessages` und Glocke?

### Recherche-Ergebnis

- In der offiziellen Contao-Dokumentation (ContaoDev, ContaoUser) wurde **keine explizite Dokumentation** zur Glocke/Header-Benachrichtigung gefunden.
- `getSystemMessages` bezieht sich klar auf die **Backend-Startseite**, nicht auf die Glocke.
- Die Glocke könnte technisch mit dem System-Log (tl_log) verknüpft sein – dies müsste im Contao-Core-Code geprüft werden (vendor-Verzeichnis ist im Projekt nicht zugänglich).

---

## 5. Erweiterungen (third-party)

### contao-alert-reminder-bundle (heimrichhannot)

- [GitHub](https://github.com/heimrichhannot/contao-alert-reminder-bundle)
- Erweiterte Funktionalität zur Erinnerung an Alerts im Backend
- Könnte die Glocke/Hinweis-Funktionalität erweitern

### terminal42/contao-notification_center

- E-Mail-Benachrichtigungen (z. B. gesperrte Konten, neue Registrierungen)
- Nicht primär für Backend-UI-Hinweise gedacht

---

## 6. Zusammenfassung

| Mechanismus        | Ort                     | Doku                                  | Im Zotero-Bundle |
|--------------------|-------------------------|---------------------------------------|------------------|
| getSystemMessages  | Backend-Startseite      | [getSystemMessages](https://docs.contao.org/dev/reference/hooks/getSystemMessages) | ✅ GetSystemMessagesSyncWarningListener |
| ContaoContext      | System-Log (tl_log)     | [Logging](https://docs.contao.org/dev/framework/logging/) | ❌ noch nicht |
| badge_title        | Badge-Titel im Header   | Manual Einstellungen                  | – nicht relevant |
| Glocke (Header)    | unklar                  | nicht gefunden                        | – |

---

## 7. Nächste Schritte (optional)

1. **ContaoContext** bei Sync-Fehlern zusätzlich nutzen → Fehler erscheinen im System-Log.
2. **Contao-Core prüfen** (falls möglich): Wie funktioniert die Glocke, welche Datenquelle nutzt sie?
3. **heimrichhannot/contao-alert-reminder-bundle** prüfen: Nutzbarkeit für Zotero-Sync-Warnungen.
