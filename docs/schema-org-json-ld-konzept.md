# Schema.org mit JSON-LD – Konzept für Zotero-Publikationen

**Stand:** Februar 2025  
**Zweck:** Recherche und Umsetzungskonzept für strukturierte Daten (JSON-LD) im Zotero-Bundle

---

## 1. Schema.org – passende Typen für Zotero-Publikationen

Zotero verwaltet wissenschaftliche und allgemeine Publikationen. Schema.org bietet dafür geeignete Typen unter `CreativeWork`:

| Zotero itemType      | Schema.org-Typ      | Beschreibung                                      |
|----------------------|---------------------|---------------------------------------------------|
| journalArticle       | **ScholarlyArticle**| Zeitschriftenartikel (Thing → CreativeWork → Article → ScholarlyArticle) |
| book                 | **Book**            | Bücher (Thing → CreativeWork → Book)              |
| bookSection          | ScholarlyArticle    | Buchkapitel (oder Article als Fallback)           |
| conferencePaper      | ScholarlyArticle    | Konferenzbeiträge                                 |
| report               | Report              | Berichte (CreativeWork → Report)                  |
| thesis               | Thesis              | Abschlussarbeiten (CreativeWork → Thesis)         |
| newspaperArticle     | NewsArticle         | Zeitungsartikel                                  |
| (generisch)          | **CreativeWork**    | Fallback für alle anderen Typen                   |

### Wichtige Properties (alle CreativeWork-Subtypen)

| Schema.org Property | Zotero-Feld / Quelle            | Anmerkung                    |
|--------------------|----------------------------------|------------------------------|
| `@type`            | item_type → Mapping (s.o.)       |                             |
| `name` / `headline`| title                           |                             |
| `abstract`         | data.abstractNote               |                             |
| `author`           | data.creators (author)           | Array von Person            |
| `creator`          | data.creators (alle)             | author, editor etc.          |
| `datePublished`    | date, year                       | ISO 8601 wenn möglich        |
| `publisher`        | data.publisher                  | Organization oder Person    |
| `isPartOf`         | publication_title               | Bei journalArticle, bookSection |
| `keywords`         | data.tags                        | Array von Tag-Namen         |
| `inLanguage`       | data.language                   | BCP 47 (z. B. de, en)       |
| `url`              | reader_url (Contao-Seite)       | Kanonische URL der Detailseite |

### Person (für author/creator)

```json
{
  "@type": "Person",
  "givenName": "Max",
  "familyName": "Mustermann"
}
```

Bei `fieldMode == 1` (Ein-Feld-Modus): `"name": "Mustermann, Max"` statt givenName/familyName.

---

## 2. Contao – JSON-LD einbetten

Contao bietet die **Twig-Funktion `add_schema_org`** zum Einbetten von JSON-LD:

- **Dokumentation:** [add_schema_org - Twig Function](https://docs.contao.org/dev/reference/twig/functions/add_schema_org/)
- **Argument:** Ein Array mit Schema.org-Daten
- **Aufruf:** `{% do add_schema_org({...}) %}` im Template (oder Daten aus dem Controller übergeben)

**Beispiel aus der Contao-Doku:**

```twig
{% do add_schema_org({
  '@type': 'Event',
  'identifier': '#/schema/events/' ~ id,
  'name': title,
  'startDate': startTime|date('Y-m-d\\TH:i:sP')
}) %}
```

**Empfehlung:** Die strukturierten Daten im **Controller** vorbereiten und als Variable ans Template übergeben – analog zu Files/Figure mit `file.schemaOrgData`.

---

## 3. Umsetzungskonzept für das Zotero-Bundle

### 3.1 Wo JSON-LD ausgeben?

| Kontext              | Ort                        | Inhalt                         |
|----------------------|----------------------------|--------------------------------|
| **Detailansicht**    | ZoteroReaderController     | Einzelnes Item als CreativeWork/ScholarlyArticle/Book |
| **Listenansicht**    | ZoteroListController       | ItemCollection (optional) oder einzelne Items |

**Fokus:** Zuerst die **Detailansicht** (Reader), da dort ein vollständiges Item mit allen Metadaten angezeigt wird.

### 3.2 Service oder Controller-Logik?

Ein **ZoteroSchemaOrgService** (oder -Helper) könnte:

1. Zotero-Item (array oder Model) entgegennehmen
2. Basierend auf `item_type` den passenden Schema.org-`@type` wählen
3. Autoren aus `data.creators` oder `tl_zotero_item_creator` (mit Reihenfolge) als Person-Objekte bauen
4. Ein valides Schema.org-Array zurückgeben

### 3.3 Integration im Reader

- **ZoteroReaderController:** Vor dem Rendern `schemaOrgData` aus dem Service holen
- **Template** (z. B. `zotero_reader.html.twig`):  
  `{% do add_schema_org(item.schema_org_data|default) %}`
- Alternativ: `schema_org_data` im Controller in `$template->schema_org` setzen

### 3.4 Zotero itemType → Schema.org @type (Mapping)

```php
// Vereinfachtes Mapping
$typeMap = [
    'journalArticle' => 'ScholarlyArticle',
    'book' => 'Book',
    'bookSection' => 'ScholarlyArticle',
    'conferencePaper' => 'ScholarlyArticle',
    'report' => 'Report',
    'thesis' => 'Thesis',
    'newspaperArticle' => 'NewsArticle',
    // default
] ?? 'CreativeWork';
```

### 3.5 Wichtige Randbedingungen

- **identifier:** Eindeutige ID, z. B. `#/schema/zotero/{{ item.id }}` oder URL zur Detailseite
- **mainEntityOfPage:** URL der aktuellen Seite (Reader-URL)
- **url:** Kanonische URL der Publikationsseite
- Validierung: [validator.schema.org](https://validator.schema.org)

---

## 4. Siehe auch

- `mehrsprachigkeit-item-fields.md` – Feld-Labels für json_dl (relevant für `inLanguage`, Abstract)
- `mehrsprachigkeit-item-types.md` – Item-Typ-Labels (Basis für @type-Mapping)
- `reader-modul-vorschlag.md` – Lesemodul-Struktur (Detailansicht = Hauptort für JSON-LD)
- `such-modul-konzept.md` – Listen-/Suchmodul (optional: ItemCollection für Suchergebnisse)

---

## 5. Quellen

- [Schema.org ScholarlyArticle](https://schema.org/ScholarlyArticle)
- [Schema.org Book](https://schema.org/Book)
- [Schema.org CreativeWork](https://schema.org/CreativeWork)
- [Schema.org Person](https://schema.org/Person)
- [Contao: add_schema_org Twig Function](https://docs.contao.org/dev/reference/twig/functions/add_schema_org/)
