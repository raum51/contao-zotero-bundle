# Mehrsprachigkeit bei Item-Feldern – Umsetzung

## 1. Konzept

Die **tl_zotero_locales**-Tabelle speichert pro Locale das JSON-Objekt **item_fields** mit Key→Label-Mapping (z. B. `{"title":"Titel","abstractNote":"Abstract","creators":"Autoren"}`).

## 2. Frontend: json_dl-Template

Das Template `json_dl.html.twig` nutzt `item.field_labels`, das vom Controller (ZoteroReaderController, ZoteroListController) basierend auf `$request->getLocale()` gesetzt wird. **Nur Felder mit lokalisiertem Label** (aktuell oder en_US-Fallback) werden ausgegeben; Felder ohne Eintrag in `tl_zotero_locales` werden übersprungen (Label == Key → kein Output).

Die Locale entspricht der **Sprache der aktuellen Seite bzw. des Website-Roots**.

## 3. Lookup-Service

`ZoteroLocaleLabelService::getItemFieldLabelsForKeys(array $keys, string $locale)` liefert für die Keys eines Items (aus `item.data`) die passenden Labels. Fallback: en-US, sonst der Original-Key.

## 4. Siehe auch

- `mehrsprachigkeit-item-types.md` – gemeinsames Konzept für Item-Typen und Item-Felder in `tl_zotero_locales`
- `schema-org-json-ld-konzept.md` – Schema.org/JSON-LD für Publikationen (nutzt u. a. abstract, creators, language)
- `ZoteroLocaleService` – API-Abruf und Befüllung
- `ZoteroLocaleLabelService` – Lookup für Labels
