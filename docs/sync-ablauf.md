# Zotero-Sync: Ablauf und Logik

**Quelle:** ZoteroSyncService, Zotero API v3  
**Stand:** 2026-02-23

---

## 1. Übersicht

Der Sync überträgt Daten von der Zotero-API in die lokalen Contao-Tabellen. **Zotero hat die Datenhoheit** – Contao spiegelt die Inhalte; Redaktion arbeitet in Zotero. Einige Felder (published, download_attachments) können in Contao zusätzlich gesteuert werden.

### Auslöser

| Auslöser | Beschreibung |
|----------|--------------|
| **Backend-Button** | „Jetzt synchronisieren“ / „Synchronisation zurücksetzen“ (pro Library oder alle). Ab Contao 5.6 asynchron via Messenger + Job-Framework. |
| **CLI** | `contao:zotero:sync` (mit Optionen `--library`, `--reset`). |
| **Cronjob** | ZoteroSyncCron (hourly) – synchronisiert nur fällige **published** Libraries mit `sync_interval > 0`. |

### Betroffene Tabellen

- `tl_zotero_collection` – Collections (inkl. Hierarchie)
- `tl_zotero_item` – Items (Publikationen, Metadaten, cite/bib)
- `tl_zotero_item_attachment` – Attachments als Kind von tl_zotero_item
- `tl_zotero_item_creator` – M:N Item ↔ Creator
- `tl_zotero_collection_item` – M:N Collection ↔ Item
- `tl_zotero_creator_map` – Sync legt neue Creators an (zotero_firstname, zotero_lastname, member_id=0); Redaktion ordnet member_id (tl_member) zu

---

## 2. Ablauf pro Library (5 Phasen)

Für jede Library läuft der Sync in festen Schritten. Bei `--reset` wird `last_sync_version` auf 0 gesetzt, sodass alle Objekte neu geholt werden (Vollabzug).

### Phase 1: fetchDeletedObjects

- **API:** `GET {prefix}/deleted?since={lastVersion}`
- **Zweck:** Alle seit der letzten Version gelöschten Collections und Items ermitteln.
- **Folge:** Keys werden in lokalen Tabellen als gelöscht behandelt (siehe Phase 2–4).

### Phase 2: syncDeletedCollections + syncDeletedItems

**Collections in /deleted:**
- `published` → 0 (nicht gelöscht, für Zotero-Recovery erhalten).
- Collection-Item-Verknüpfungen bleiben bestehen.

**Items in /deleted:**
- Lokale Items werden physisch gelöscht (inkl. Kind-Daten: Attachments, Item-Creators, Collection-Items).

### Phase 3: syncCollections

- **API:** `GET {prefix}/collections?start=&limit=100&includeTrashed=1`
- **Aktionen:**
  - Neue Collections einfügen (mit `published` aus Zotero `deleted`).
  - Bestehende: Titel und `published` aktualisieren, wenn geändert.
  - Collections, die in Zotero gelöscht sind, depublizieren (`published=0`).
- **Hierarchie:** Parent-IDs werden in einem zweiten Durchlauf gesetzt.

### Phase 4: syncItems (2-Pass)

**Pass 1 – Items (ohne Attachments):**
- **API:** `GET {prefix}/items?itemType=-attachment&start=&limit=100&since=…`
- Items werden eingefügt oder aktualisiert.
- Für jedes Item: Zusätzliche API-Requests für cite_content (HTML) und bib_content (BibTeX).
- **Felder:** Siehe [felder-sync-uebersicht.md](felder-sync-uebersicht.md).
- **trash:** Wird aus Zotero `data.deleted` gesetzt. **published** wird **nicht** vom Sync überschrieben (nur bei Insert = 1).

**Pass 2 – Attachments:**
- **API:** `GET {prefix}/items?itemType=attachment&start=&limit=100&since=…`
- Attachments werden als Kind von tl_zotero_item gespeichert.
- **trash** aus Zotero; **published** bei Update unverändert (bei Insert = 1).

### Phase 5: syncCollectionItems

- **API:** `GET {prefix}/collections/{key}/items` pro Collection
- Ermittelt, welche Items in welcher Collection liegen.
- Löscht Verknüpfungen in `tl_zotero_collection_item`, die in Zotero nicht mehr vorhanden sind.
- **Ausnahme:** Items mit `trash=1` (Zotero-Papierkorb) werden beim Löschen übersprungen – bei Wiederherstellung in Zotero sind sie wieder in der Collection.
- Fügt neue Verknüpfungen ein.

### Abschluss: updateLibrarySyncStatus

- `last_sync_version` = Version aus dem letzten API-Response
- `last_sync_at`, `last_sync_status` werden aktualisiert

---

## 3. Trennung: published vs. trash

| Feld | Bedeutung | Von wem gesetzt? | Frontend-Filter |
|------|-----------|------------------|-----------------|
| **published** | Contao-Veröffentlichung (im Frontend anzeigen) | Redaktion (Toggle) | `published = 1` |
| **trash** | Zotero-Papierkorb (deleted) | **Nur Sync** (disabled im Backend) | `trash = 0` |

- **Frontend:** Es werden nur Items/Attachments mit `published = 1` **und** `trash = 0` angezeigt.
- **Sync:** Überschreibt **trash** aus `data.deleted`; **published** bleibt unverändert (außer bei neuem Insert = 1).
- **Collections:** Haben nur `published` (kein trash). Zotero-Papierkorb → `published = 0`.

---

## 4. Inkrementell vs. Voll-Sync

| Modus | last_sync_version | API-Parameter | Verhalten |
|-------|-------------------|---------------|-----------|
| **Inkrementell** | > 0 | `since={version}` | Nur geänderte/gelöschte Objekte seit letztem Sync. |
| **Voll (Reset)** | 0 | `since=0` oder ohne | Alle Collections und Items werden neu geholt; gelöschte werden via /deleted bereinigt. |

---

## 5. Felder: Vom Sync überschrieben vs. Redaktion

Siehe [felder-sync-uebersicht.md](felder-sync-uebersicht.md) für die detaillierte Tabelle pro Tabelle.

**Kurz:**
- **Überschrieben:** zotero_key, alias, title, cite_content, bib_content, json_data, tags, trash, …
- **Nicht überschrieben (Redaktion):** published, download_attachments (bei Update).
- **Nur bei Insert:** download_attachments = 0; published = 1.

---

## 6. API-Endpoints (Zotero v3)

| Endpoint | Verwendung |
|----------|------------|
| `{prefix}/deleted?since=` | Gelöschte Collections/Items/Tags. |
| `{prefix}/collections` | Collections inkl. Trash. |
| `{prefix}/items?itemType=-attachment` | Items ohne Attachments. |
| `{prefix}/items?itemType=attachment` | Attachments. |
| `{prefix}/items/{key}?include=bib` | cite_content (HTML). |
| `{prefix}/items/{key}?format=bibtex` | bib_content. |
| `{prefix}/collections/{key}/items` | Items pro Collection. |

`{prefix}` = `users/{libraryId}` oder `groups/{groupId}`.

---

## 7. Fehler und Übersprungenes

- **Übersprungene Items:** Z. B. Attachment ohne Parent in Zotero – werden geloggt (Systemlog, Result-Array, `--log-skipped`).
- **API-Fehler:** Retry mit Backoff (ZoteroClient); danach Fehler in Result und Logger.
- **Library-Fehler:** `last_sync_status` wird mit Fehlermeldung gesetzt; nächster Sync versucht erneut.
