# raum51/contao-zotero-bundle

Contao-Bundle zur Integration der Zotero API (Items, Gruppen, Benutzer-Bibliotheken, Collections) mit lokalem Caching, Autoren-Zuordnung zu Contao-Mitgliedern und Frontend-Ausgabe.

- **Ziel-Umgebung:** Contao 5.6+ / PHP 8.2+
- **Lizenz:** MIT

Siehe Projekt-Blueprint im Contao-Projekt-Root: `CURSOR_BLUEPRINT.md`

---

## Console-Commands (contao:zotero:*)

Alle Befehle im Projekt-Root ausführen (dort, wo `bin/console` liegt). Backend-Buttons „Jetzt synchronisieren“ und „Synchronisation zurücksetzen“ nutzen dieselbe Logik über den `ZoteroSyncService` (nicht den CLI-Command), sodass Verhalten und Ergebnis identisch sind.

### contao:zotero:sync

Synchronisiert Zotero-Bibliotheken (Collections, Items, Zitate, BibTeX) in die lokalen Tabellen.

| Option | Kurz | Beschreibung |
|--------|-----|--------------|
| `--library=ID` | `-l` | Nur diese Library-ID synchronisieren (ohne Option: alle). |
| `--reset` | `-r` | Sync-Metadaten vor dem Abruf zurücksetzen (Vollabzug, wie „Synchronisation zurücksetzen“ im Backend). |
| `--log-skipped=PATH` | – | Übersprungene Items in JSON-Datei schreiben (z. B. `var/logs/zotero_skipped.json`). Verzeichnis wird ggf. angelegt. |
| `--log-changes=PATH` | – | Erstellte, aktualisierte und gelöschte Items/Attachments/Collections in JSON-Datei schreiben (z. B. `var/logs/zotero_changes.json`). Mit `--show-details` werden die Details zusätzlich als Tabellen angezeigt. |
| `--show-details` | – | Detail-Tabellen anzeigen (übersprungene Items, erstellte/aktualisierte/gelöschte Einträge). Ohne diese Option wird nur die Zusammenfassung ausgegeben. |
| `--debug` | – | Debug-Info: Rohdaten aller API-Endpoints (/deleted, /collections, /items, /collections/{key}/items) und lokaler Entitäten (tl_zotero_*). Erfordert `-l/--library`. Hilft bei Fehlersuche, z. B. wenn Collection-Löschungen nicht übernommen werden. |

Für detaillierte Ausgaben (API-Requests, Fehlerdetails) die Standard-Optionen von Symfony Console verwenden: **`-v`** (verbose) oder **`-vv`** (sehr ausführlich). Beispiel: `php bin/console contao:zotero:sync -l 11 -vv`.

**Beispiele:**

```bash
# Alle Libraries synchronisieren
php bin/console contao:zotero:sync

# Nur Library mit ID 11
php bin/console contao:zotero:sync --library=11

# Vollabzug: Sync zurücksetzen und dann alle Libraries syncen
php bin/console contao:zotero:sync --reset

# Vollabzug nur für Library 11
php bin/console contao:zotero:sync --reset --library=11

# Übersprungene Items in Datei protokollieren
php bin/console contao:zotero:sync -l 11 --log-skipped=var/logs/zotero_skipped.json

# Änderungsdetails in Datei schreiben (Tabellen nur mit --show-details)
php bin/console contao:zotero:sync -l 11 --log-changes=var/logs/zotero_changes.json

# Detail-Tabellen anzeigen (übersprungene Items, erstellte/aktualisierte/gelöschte Einträge)
php bin/console contao:zotero:sync --show-details

# Debug-Info (alle API-Endpoints und lokale Entitäten) – erfordert Library-ID
php bin/console contao:zotero:sync -l 1 --debug
```

Bei großen Bibliotheken kann der Sync im Backend zu Timeouts führen; dann den Sync per CLI ausführen (kein Request-Timeout, kein Browser-Abbruch).

**Items-Abruf (2-Pass):** Der Sync ruft zuerst alle Nicht-Attachments (`itemType=-attachment`) und anschließend alle Attachments (`itemType=attachment`) ab. So stehen Parent-Items immer vor deren Attachments zur Verfügung und Reihenfolge-Probleme entfallen.

**Übersprungene Items:** Nicht importierbare Items (z. B. Attachment ohne Parent, API-Fehler) werden protokolliert: im Log (Kanal `raum51_zotero`), im Result-Array und – bei CLI-Ausführung mit `--show-details` – als Tabelle mit Key, Typ, Grund und Library. Mit `--log-skipped=PATH` werden sie zusätzlich in eine JSON-Datei geschrieben (Format: `synced_at`, `count`, `skipped_items`).

### contao:zotero:item

Ruft das JSON eines einzelnen Zotero-Items über die API ab und gibt es aus (oder schreibt es mit `--log-api` in eine Datei). Durchläuft alle konfigurierten Libraries, bis das Item gefunden wird – oder nutzt bei Angabe einer `tl_zotero_item`-ID die bekannte Library direkt. Zotero-Keys können library-spezifisch sein (gleicher Key = unterschiedliche Items in verschiedenen Libraries).

| Argument/Option | Kurz | Beschreibung |
|-----------------|-----|--------------|
| `item` | – | Zotero-Item-Key (z. B. `ABC123`) oder `tl_zotero_item.id` (Pflichtargument). |
| `--library=ID` | `-l` | Nur diese Library-ID durchsuchen (ohne Option: alle Libraries). |
| `--find-all` | – | Ohne `--library`: Alle Libraries durchsuchen und alle Treffer als JSON-Array ausgeben (Keys können library-spezifisch sein). |
| `--log-api=PATH` | – | API-Aufrufe als JSON in diese Datei schreiben (gleiches Format wie bei `contao:zotero:sync`). |

**Beispiele:**

```bash
# Item per Zotero-Key aus allen Libraries suchen
php bin/console contao:zotero:item ABC123

# Nur in Library 1 suchen
php bin/console contao:zotero:item ABC123 --library=1

# Alle Libraries durchsuchen, alle Treffer ausgeben (gleicher Key in mehreren Libraries)
php bin/console contao:zotero:item ABC123 --find-all

# Item per tl_zotero_item.id (nutzt die passende Library direkt)
php bin/console contao:zotero:item 42

# Mit API-Log in Datei
php bin/console contao:zotero:item ABC123 --log-api=var/logs/zotero_item_api.json
```

---

## Frontend-Routen (Bib-Export & Attachments)

Die Routen werden im Bundle über `Resources/config/routes.yaml` definiert und in der **Contao Managed Edition** automatisch per **RoutingPluginInterface** im Manager-Plugin geladen (vor dem Contao-Content-Routing). Kein manueller Eintrag in der App nötig.

**Ohne Managed Edition** (z. B. eigenes Symfony-Projekt mit dem Bundle): Nach der Installation einmal ausführen:

```bash
php bin/console contao:zotero:install-routes
```

Der Befehl legt in der Contao-Installation die Datei `config/routes.yaml` an bzw. ergänzt sie um den Import der Zotero-Bundle-Routen. Anschließend Cache leeren (z. B. `php bin/console cache:clear`).

Falls Sie den Import manuell eintragen möchten: In `config/routes.yaml` der App den Block aus dem Befehl `contao:zotero:install-routes` (oder den folgenden) einfügen:

```yaml
Raum51ContaoZoteroBundle:
    resource: '@Raum51ContaoZoteroBundle/Resources/config/routes.yaml'
```

| Route | Beschreibung |
|-------|--------------|
| `GET /zotero/export/item/{id}.bib` | Einzelnes Zotero-Item als .bib-Datei (gespeichertes **bib_content**). |
| `GET /zotero/export/list.bib` | Liste als .bib. Query: `ids=1,2,3` oder `collection=123` oder `library=1`. |
| `GET /zotero/attachment/{id}` | Attachment-Datei streamen (id = tl_zotero_item, item_type muss „attachment“ sein). Prüfung: Library- und Item-`download_attachments` müssen erlaubt sein. |

Zugriff nur auf publizierte Items und publizierte Libraries. Modul-Ebene für `download_attachments` kommt mit den Frontend-Modulen (Phase 4).

---

## Logging (Kanal raum51_zotero)

Das Bundle schreibt alle Sync- und API-Logs in den Monolog-Kanal **raum51_zotero**. So können Zotero-Logs getrennt von den allgemeinen App-Logs geführt werden.

**Eigene Log-Datei für Zotero (optional):** In der Contao-Installation unter `config/packages/monolog.yaml` (oder in der Managed-Edition unter den Package-Configs) einen Handler für den Kanal anlegen:

```yaml
monolog:
  channels: [raum51_zotero]
  handlers:
    zotero:
      type: stream
      path: "%kernel.logs_dir%/raum51_zotero.log"
      level: info
      channels: [raum51_zotero]
```

Ohne diese Konfiguration landen die Zotero-Logs im Standard-App-Log (z. B. `var/logs/prod-*.log`). Mit dem Handler erscheinen sie zusätzlich in `var/logs/raum51_zotero.log`.

---

### contao:zotero:test-client

Prüft die Verbindung zur Zotero API und den API-Key. Dient außerdem dazu, die **Group-ID** für eine Zotero-Gruppenbibliothek zu ermitteln (für `tl_zotero_library` bei `library_type=group`).

| Option | Beschreibung |
|--------|--------------|
| `--api-key=KEY` | Zotero-API-Key (erforderlich). Erstellen unter [Zotero – API Keys](https://www.zotero.org/settings/keys). |
| `--list-groups` | Alle Gruppen des Keys anzeigen (Group-ID und Name). Group-ID in Literaturverwaltung → Library als **Library-ID** eintragen, **library_type** = group. |
| `--path=PATH` | Beliebiger API-Pfad (z. B. `/users/USER_ID/items`) zum Testen. |

**Beispiele:**

```bash
# Key validieren (User-ID und Rechte anzeigen)
php bin/console contao:zotero:test-client --api-key=DEIN_ZOTERO_API_KEY

# Gruppen auflisten (Group-ID für library_id)
php bin/console contao:zotero:test-client --api-key=DEIN_KEY --list-groups

# Items-Abruf testen
php bin/console contao:zotero:test-client --api-key=DEIN_KEY --path=/users/DEINE_USER_ID/items
```

---

## Zitierstile (citation_style)

Das Feld **citation_style** in `tl_zotero_library` wird bei der Zotero API für formatierte Literaturverweise (`include=bib`) verwendet. Erlaubt ist entweder der **Dateiname ohne .csl** eines Stils aus dem [Zotero Style Repository](https://www.zotero.org/styles) oder die **URL** einer externen CSL-Datei.

**Häufig verwendete Zitierstile (Name ohne .csl eintragen):**

| Stil-Name (für citation_style) | Beschreibung |
|--------------------------------|--------------|
| `apa` | American Psychological Association (7th edition) |
| `chicago-note-bibliography` | Chicago (Fußnoten + Bibliographie), API-Standard |
| `chicago-author-date` | Chicago (Autor-Jahr) |
| `mla` | Modern Language Association |
| `harvard1` | Harvard (variante 1) |
| `din-1505-2` | DIN 1505-2 (deutsch) |
| `ieee` | IEEE |
| `nature` | Nature |
| `vancouver` | Vancouver (Nummernstil) |
| `gb-t-7714-2015` | Chinesischer Nationalstandard GB/T 7714-2015 |

**Deutschsprachige Zitierstile (Name ohne .csl eintragen):**

| Stil-Name (für citation_style) | Beschreibung |
|--------------------------------|--------------|
| `deutsche-gesellschaft-fur-psychologie` | DGPs (Deutsche Gesellschaft für Psychologie), Richtlinien zur Manuskriptgestaltung |
| `din-1505-2` | DIN 1505-2 (deutsche Norm für Literaturverzeichnisse) |
| `zeitgeschichte` | Zeitschrift „Zeitgeschichte“ (österreichisch-deutsch) |
| `deutsch-fussnoten` | Deutsche Zitierweise (Fußnoten) |
| `deutsche-gesellschaft-fur-erziehungswissenschaft` | DGfE (Deutsche Gesellschaft für Erziehungswissenschaft) |
| `psychologie-in-geschichte-und-gegenwart` | Psychologie in Geschichte und Gegenwart |
| `soziologie-deutsch` | Soziologie (deutsche Zeitschrift) |
| `historische-zeitschrift` | Historische Zeitschrift (HZ) |
| `medizin-deutsch` | Medizin (deutsche Fachzeitschriften) |
| `linguistik-in-deutschland` | Linguistik in Deutschland |

Die vollständige Liste (über 10.000 Stile) durchsuchst du im [Zotero Style Repository](https://www.zotero.org/styles); der dort angezeigte Stil-Name (ohne Endung) ist der Wert für **citation_style**. Ungültige oder leere Werte führen beim Sync zu keiner Zitierausgabe; Platzhalter wie „CSL-URL“ können zu API-Fehlern führen.
