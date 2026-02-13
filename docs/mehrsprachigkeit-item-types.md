# Mehrsprachigkeit bei Item-Typen und Item-Feldern – Umsetzung

## 1. Konzept

Die Tabelle **tl_zotero_locales** speichert pro Locale die lokalisierten Bezeichnungen für:
- **Item-Typen** (book, journalArticle, …) – aus `GET /itemTypes?locale=XX`
- **Item-Felder** (title, abstractNote, creators, …) – aus `GET /itemFields?locale=XX`

**Spalten:** `id`, `tstamp`, `locale`, `item_types` (JSON), `item_fields` (JSON).

**Locale-Format:** Intern Contao-Syntax (`de_AT`, `en_US`). Bei API-Aufrufen Konvertierung zu BCP-47 (`de-AT`) nur an der Zotero-API-Grenze.

## 2. Befüllung

- **Command:** `contao:zotero:fetch-locales`
- **Automatisch:** Bei jedem Sync (CLI und Backend-Button)
- **Locales:** en_US (Fallback), de_DE, plus jede `citation_locale` der Libraries und jede Sprache der Website-Roots (`tl_page` mit `type=root`)

## 3. Nutzung

### Backend
- Filter nach Item-Typ (Listen-/Such-Modul): Labels aus `tl_zotero_locales` für die Backend-Sprache
- Lookup-Service: `ZoteroLocaleLabelService::getItemTypeLabel($itemType, $locale)`

### Frontend
- **json_dl-Template:** Feld-Labels entsprechen der Sprache der aktuellen Seite bzw. des Website-Roots (`$request->getLocale()`)
- Lookup-Service: `ZoteroLocaleLabelService::getItemFieldLabelsForKeys($keys, $locale)`

## 4. Strukturelle Keys (creators, tags, collections, relations)

Diese Keys liefert `/itemFields` nicht. Sie werden im `ZoteroLocaleService` als statische Ergänzung pro Locale hinterlegt (de-DE: „Autoren“, „Schlagwörter“, …; en-US: „Creators“, „Tags“, …).

## 5. Siehe auch

- `schema-org-json-ld-konzept.md` – Schema.org-Mapping von Zotero itemType (journalArticle, book, …) zu Schema.org @type
