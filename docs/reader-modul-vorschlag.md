# Reader-Modul – Umsetzungsvorschlag

**Stand:** 12. Februar 2025  
**Basis:** Contao News/Events-Pattern, [Content Routing](https://docs.contao.org/dev/framework/routing/content-routing), [Legacy Parameters](https://docs.contao.org/dev/framework/routing/legacy-parameters)

---

## 1. Übersicht

Das Reader-Modul ermöglicht die Detailansicht eines Zotero-Items. Die Ziel-URL und das Rendering folgen dem **Contao-News/Events-Standard**:

- **Archiv (Library):** Pflichtfeld `jumpTo` → Zielseite mit Reader
- **Listenmodul:** Optionales Feld `zotero_reader_module` → überschreibt die Zielseite
- **URL-Struktur:** Contao-Bordmittel (keine eigene Route) – `{Seiten-URL}/{alias}` wird als `auto_item` gesetzt
- **Liste + Reader kombiniert:** Wenn Reader-Modul im Listenmodul gewählt ist, rendert die Liste bei gesetztem `auto_item` das Reader-Modul anstelle der Liste

---

## 2. URL-Mechanik (Contao-Bordmittel)

Contao verwendet den **InputEnhancer** und **Legacy Parameters**:

- Seite `news/detail` mit URL `https://example.com/news/detail`
- Zusätzlicher Pfad: `https://example.com/news/detail/mein-artikel`
- Contao setzt `auto_item = 'mein-artikel'` (bei ungerader Fragment-Anzahl)
- Module können `Input::get('auto_item')` auslesen; der Reader sucht per Alias/ID das Item

**Keine eigene Route nötig** – der bestehende Page-Route nimmt den Pfad `{!parameters}` auf.

---

## 3. DCA-Änderungen

### 3.1 tl_zotero_library

Neues Pflichtfeld **`jumpTo`** (pageTree):

- **Palette:** `{title_legend},title;{frontend_legend},jumpTo;{zotero_legend},...`
- **Feld:** `inputType: pageTree`, `eval: mandatory`, `relation: tl_page`
- **Sprache:** „Weiterleitungsseite“ / „Redirect page“

**Schema:** `jumpTo int(10) unsigned NOT NULL default 0` – Contao-Schema-Update erzeugt die Spalte (keine Migration in Entwicklungsphase, wenn Tabelle noch leer/neu).

### 3.2 tl_module – zotero_list

Neues optionales Feld **`zotero_reader_module`** (select, Module vom Typ `zotero_reader`):

- **Palette:** Erweiterung um `zotero_reader_module` in `{zotero_legend}`
- **Feld:** Select mit Options-Callback, der nur `zotero_reader`-Module listet (optional gefiltert nach Library)
- **Sprache:** „Lesemodul“ / „Reader module“

### 3.3 tl_module – zotero_reader

Palette existiert bereits. Felder:

- `zotero_library` (bereits vorhanden)
- `zotero_template` (bereits vorhanden)

---

## 4. Ziel-URL-Logik

| Konstellation | Ziel für Item-Links |
|---------------|----------------------|
| `zotero_reader_module` gesetzt | **Aktuelle Seite** (Liste + Reader auf derselben Seite) |
| `zotero_reader_module` nicht gesetzt | **Library.jumpTo** |

Bei gesetztem Reader-Modul bleibt der Nutzer auf der Listen-Seite; durch Anhängen von `/{alias}` wird `auto_item` gesetzt und das Listen-Modul rendert das Reader-Modul statt der Liste.

---

## 5. Controller-Logik

### 5.1 ZoteroListController

1. **Vor dem Rendern prüfen:**
   - `zotero_reader_module > 0` **und** `Input::get('auto_item') !== null`
   - → Reader-Modul rendern (per Fragment-Renderer), sonst Liste rendern

2. **Ziel-URL pro Item:**
   - Wenn `zotero_reader_module` gesetzt: `$request->getUri()` (Basis-URL der aktuellen Seite) oder `PageModel` der aktuellen Seite + `/{alias}`
   - Sonst: `library.jumpTo`-Seite + `/{alias}`

3. **Template:** Jedes Item erhält `reader_url` (z.B. `/publikationen/detail/mein-buch`)

### 5.2 ZoteroReaderController (neu)

- **Typ:** `zotero_reader`, `category: miscellaneous`
- **Template:** `frontend_module/zotero_reader`
- **Logik:**
  1. `Input::get('auto_item')` auslesen
  2. Wenn `null` → leeren String zurückgeben (Reader ohne Item = unsichtbar)
  3. Item per `findPublishedByParentAndIdOrAlias(alias, libraryId)` laden (Library aus Modul-Konfiguration)
  4. Wenn nicht gefunden → 404
  5. Item und Template an Twig übergeben, Detail-Ansicht rendern

### 5.3 Fragment-Rendering (Liste → Reader)

Wenn die Liste das Reader-Modul rendern soll, nutzt der List-Controller das **`frontend_module`**-Twig-Template (Contao 5.2+):

- Controller setzt `show_reader = true` und `reader_module_id = $model->zotero_reader_module`
- Template verzweigt: bei `show_reader` nur `{{ frontend_module(reader_module_id) }}` ausgeben, sonst die normale Liste
- Das Reader-Modul wird als Fragment gerendert und erhält dieselbe Request mit `auto_item`; es lädt das Item und zeigt die Detailansicht

**Alternative (Response direkt im Controller):** `FragmentRenderer::render(ModuleFragment::fromModel($readerModel))` – dann kein Template-Wechsel nötig, Controller gibt die Reader-Response zurück.

---

## 6. ZoteroItemModel & findPublishedByParentAndIdOrAlias

Für den Reader und optional den ContentUrlResolver wird ein **ZoteroItemModel** benötigt:

- **Pfad:** `src/Model/ZoteroItemModel.php`
- **Basis:** `Contao\Model`, `$strTable = 'tl_zotero_item'`
- **Registrierung:** `$GLOBALS['TL_MODELS']['tl_zotero_item'] = ZoteroItemModel::class` (in `config.php` oder Extension)

**Statische Methode** (analog zu NewsModel):

```php
public static function findPublishedByParentAndIdOrAlias($val, $pid)
{
    // WHERE pid=? AND published=1 AND (id=? OR alias=?)
    // LIMIT 1
}
```

---

## 7. ContentUrlResolver (optional)

Für `content_url(zoteroItem)` in Templates und z.B. Sitemap:

- **Interface:** `ContentUrlResolverInterface`
- **resolve():** Library via `item->pid` laden, `library->jumpTo`-Seite als Ziel
- **getParametersForContent():** `['parameters' => '/' . ($item->alias ?: $item->id)]`
- **Tag:** `contao.content_url_resolver` (Auto-Configuration)

Der Resolver kennt **keinen** Reader-Modul-Override; der gilt nur im Listenmodul.

---

## 8. Templates

### 8.1 zotero_list.html.twig

- Jedes `item` erhält `reader_url`
- Link: `<a href="{{ item.reader_url }}">` (z.B. um den Zitat-Text)
- Optional: Nur verlinken, wenn `reader_url` gesetzt (Library ohne jumpTo?)

### 8.2 zotero_reader.html.twig (neu)

- Erwartet: `item` (vollständige Item-Daten)
- Nutzt bestehendes `zotero_item/fields.html.twig` oder `cite_content.html.twig` für die Darstellung
- Optional: Erweiterte Detail-Ansicht (mehr Felder, Bib-Download-Button etc.)

---

## 9. Dateien-Übersicht

| Aktion | Datei |
|--------|-------|
| **Neu** | `src/Controller/FrontendModule/ZoteroReaderController.php` |
| **Neu** | `src/Model/ZoteroItemModel.php` |
| **Neu** | `src/Routing/ZoteroItemContentUrlResolver.php` (optional) |
| **Neu** | `Resources/contao/templates/frontend_module/zotero_reader.html.twig` |
| **Ändern** | `Resources/contao/dca/tl_zotero_library.php` (jumpTo) |
| **Ändern** | `Resources/contao/dca/tl_module.php` (zotero_reader_module, ggf. zotero_template für Reader) |
| **Ändern** | `src/Controller/FrontendModule/ZoteroListController.php` (Reader-Weiche, URL-Berechnung) |
| **Ändern** | `Resources/contao/templates/frontend_module/zotero_list.html.twig` (Links) |
| **Ändern** | `Resources/contao/config/config.php` (TL_MODELS) |
| **Ändern** | Sprachdateien `tl_zotero_library`, `tl_module` |

---

## 10. Reihenfolge der Umsetzung

1. **ZoteroItemModel** anlegen + in `config.php` registrieren
2. **tl_zotero_library:** Feld `jumpTo` ergänzen (DCA + Schema-Update)
3. **tl_module:** Feld `zotero_reader_module` für `zotero_list`; Reader-Palette prüfen
4. **ZoteroReaderController** + Template `zotero_reader`
5. **ZoteroListController:** Reader-Weiche, URL-Berechnung, `reader_url` an Template
6. **zotero_list.html.twig:** Links einbauen
7. **ZoteroItemContentUrlResolver** (optional)
8. **Sprachdateien** ergänzen

---

## 11. Erledigte / umgesetzte Entscheidungen

1. **Fragment-Rendering (News-Bundle):** Das News-Modul nutzt `$this->getFrontendModule($this->news_readerModule, $this->strColumn)` in `ModuleNewsList::generate()`. Beim Zotero Fragment-Controller wird stattdessen die Twig-Funktion `{{ frontend_module(reader_module_id) }}` verwendet – sie ruft intern dieselbe Controller-Logik auf. Wenn `show_reader` und `reader_module_id` gesetzt sind, rendert das Listen-Template nur das Reader-Modul.

2. **jumpTo:** Default 0 (optional), keine Migration nötig – Schema-Update über DCA.

3. **Reader-Modul-Filter:** Alle Zotero-Reader-Module werden angezeigt (`ZoteroReaderModuleOptionsCallback`).
