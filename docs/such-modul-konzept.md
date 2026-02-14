# Konzept: Zotero-Such-Modul für das Frontend

**Stand:** 14. Februar 2025  
**Bundle:** raum51/contao-zotero-bundle  
**Ziel:** Vergleich verschiedener Ansätze zur Umsetzung eines Such-Moduls sowie Empfehlung einer Contao-konformen Lösung. Abschnitt 7: Erweiterungskonzept Version 2 (Februar 2025).

---

## 1. Ausgangslage und Anforderungen

### 1.1 Aus dem Blueprint (CURSOR_BLUEPRINT.md 5.3, 5.4)

- **Suche** in Titel, Tags und Abstract
- **Filter:** Autoren (Contao-Member aus `tl_zotero_creator_map`), Erscheinungsjahr
- **Backend:** Konfiguration der Libraries, die durchsucht werden
- **Contao-Suchindex:** Optional Publikationen (Titel, Tags, Autor) in den Contao-Suchindex aufnehmen
- **Ergebnisausgabe:** Über das bestehende **Listen-Modul** (ZoteroListController)

### 1.2 Technische Basis

- **Daten:** `tl_zotero_item` mit `title`, `year`, `tags` (JSON), `json_data` (enthält Abstract, Creators etc.)
- **Autor-Filter:** Über `tl_zotero_item_creator` → `tl_zotero_creator_map` → `tl_member` (nur Einträge mit `member_id` gesetzt)
- **Listen-Modul:** `ZoteroListController` mit `fetchItems()` – aktuell ohne Such-Parameter

---

## 2. Analyse: Contao-Standards für Suche und Filter

### 2.1 Contao „Application - Search Engine“ (mod_search)

**Quellen:** [Website search](https://docs.contao.org/manual/en/layout/module-management/website-search), [Create a search form](https://docs.contao.org/manual/en/form-generator/create-a-search-form)

**Kernprinzipien:**

| Aspekt | Contao-Suchmodul |
|--------|------------------|
| **GET-Parameter** | `keywords` (Pflicht für Textsuche), `query_type` (optional: `and`/`or`) |
| **Formular** | GET als Übertragungsmethode; Feldname `keywords` |
| **Trennung Formular/Ergebnis** | Modul zeigt Formular + Ergebnisse auf einer Seite **oder** leitet per „Weiterleitungsseite“ auf andere Seite weiter (dort nur Ergebnisse) |
| **Ergebnislogik** | Ein Modultyp liest Request-Parameter und schaltet damit in den „Suchmodus“ |
| **Eigene Formulare** | Form-Generator: GET, Feld `keywords`, optional `query_type`; HTML-Modul: gleiches Schema |

**Erkenntnis:** Es gibt **keinen separaten „Ergebnis-Modultyp“** – ein Modul liest `keywords` und rendert entsprechend Formular und/oder Ergebnisliste.

### 2.2 Contao Listing-Bundle (list_table)

**Quelle:** `vendor/contao/listing-bundle/` – generisches Listen-Modul für beliebige Tabellen

**Kernprinzipien:**

| Aspekt | Listing-Bundle |
|--------|----------------|
| **GET-Parameter** | `search` = zu durchsuchendes Feld, `for` = Suchwert |
| **Konfiguration** | `list_search` = kommagetrennte Liste durchsuchbarer Felder |
| **Formular** | Eingebettet im Modul (Select für Feld + Textfeld für Wert) |
| **Filterlogik** | `Input::get('search')` und `Input::get('for')` → dynamische WHERE-Klausel |

**Erkenntnis:** Andere Namenskonvention (`search`/`for`) als das Suchmodul; Listen- und Such-Funktion in **einem** Modul; Formular und Ergebnisse auf **derselben** Seite.

### 2.3 News-/Calendar-Bundle (Jahr/Monat/Tag-Filter)

**Quelle:** `ModuleNewsArchive`, `ModuleEventMenu`, `ModuleEventlist` usw.

**Kernprinzipien:**

| Aspekt | News/Calendar |
|--------|---------------|
| **GET-Parameter** | `year`, `month`, `day` (einzeln oder kombiniert) |
| **Reader-Pattern** | `auto_item` für Detailansicht; bei gesetztem `auto_item` zeigt List-Modul den Reader |
| **Filter** | Direkte Abfrage nach Datumsfeldern |

**Erkenntnis:** Etabliertes Muster für **facettierte Filter** (Jahr, Monat) über GET-Parameter; zwei Modi (Liste vs. Detail) in einem Modul.

### 2.4 MetaModels – Filter-System

**Quellen:** [Components](https://metamodels.readthedocs.io/en/latest/manual/component/), [Define filters](https://metamodels.readthedocs.io/en/latest/manual/component/filter.html)

**Kernprinzipien:**

| Aspekt | MetaModels |
|--------|------------|
| **Architektur** | Filter-Sets mit Filter-Regeln; AND/OR verkettbar |
| **GET/POST** | Filter-Regeln können dynamisch durch GET/POST-Parameter beeinflusst werden |
| **Typen** | Text filter, Single/Multiple selection, Value from/to, Yes/No, Custom SQL |
| **Frontend** | Content-Element „MetaModel frontend filter“ + „MetaModel list“ – Trennung Filter-UI und Ergebnisliste |

**Erkenntnis:** MetaModels trennt klar **Filter-UI** (Formular) und **Listen-Ausgabe**; Filter-Werte werden über Request-Parameter übergeben. Für Zotero reicht ein schlankeres Modell – wir brauchen kein vollständiges Filter-Set-System.

---

## 3. Vergleich der Umsetzungsansätze

### Ansatz A: Such-Modul = nur Formular, Listen-Modul = beide Modi (Blueprint-Empfehlung)

**Beschreibung:**

- **Zotero-Such-Modul:** Nur Formular (keywords, optional Autor-Dropdown, optional Jahr Von/Bis). Keine Ergebnislogik.
- **Listen-Modul:** Liest `keywords`, `zotero_author`, `zotero_year_from`/`zotero_year_to`; bei Vorhandensein → Suchmodus, sonst Listenmodus.
- **Platzierung:** Such-Modul im Header oder auf eigener Seite; Weiterleitungsseite = Seite mit Listen-Modul.

**Vorteile:**

- Entspricht **Contao Search Engine** (Formular → Weiterleitung → Ergebnisse)
- Klare Trennung: Such-Modul = UX, Listen-Modul = Logik
- GET-Parameter ermöglichen **bookmarkbare/freigebare** Suchergebnisse
- Ein Listen-Modul für beide Fälle – keine Duplikation der Ergebnis-Darstellung

**Nachteile:**

- Zwei Module müssen konfiguriert und verknüpft werden (Weiterleitungsseite, ggf. Referenz Listen→Such)
- Seitenaufbau etwas aufwendiger (zwei Module anlegen)

---

### Ansatz B: Kombiniertes Modul (Formular + Ergebnisse wie Listing-Bundle)

**Beschreibung:**

- **Ein Modul:** Zeigt Suchformular und Ergebnisse auf derselben Seite.
- Keine Weiterleitung; Modul enthält beides (wie Contao Search Engine mit „keine Weiterleitungsseite“).

**Vorteile:**

- Eine Modul-Instanz, eine Seite – einfache Konfiguration
- Üblich bei Contao Search Engine, wenn keine Weiterleitung gewählt wird

**Nachteile:**

- Formular und Liste müssen im selben Modul gerendert werden – mehr Logik in einem Controller
- Weniger Flexibilität beim Layout (z. B. Formular im Header, Ergebnisse im Content)

---

### Ansatz C: Listen-Modul mit eingebettetem Suchformular (wie Listing-Bundle)

**Beschreibung:**

- **Nur Listen-Modul:** Enthält optional ein Suchformular oberhalb der Liste.
- Wenn `list_search`-ähnliche Option aktiv: Formular einblenden; Parameter wie bei Ansatz A.

**Vorteile:**

- Maximale Einfachheit: ein Modultyp, alles an einem Ort
- Ähnlich Listing-Bundle (`search`/`for`)

**Nachteile:**

- Suchformular kann nicht separat z. B. im Header stehen
- Weniger Contao-konform als getrenntes Such-Modul (Contao bietet explizit „Suchformular im Header“ + Weiterleitung)

---

### Ansatz D: Eigenständiges Such-Ergebnis-Modul

**Beschreibung:**

- **Such-Modul:** Formular, leitet mit GET weiter.
- **„Suchergebnis-Modul“:** Neuer Modultyp, der nur Ergebnisse anzeigt (liest GET, rendert Liste).

**Vorteile:**

- Strikte Trennung von Formular und Ergebnissen

**Nachteile:**

- **Nicht Contao-konform:** Contao nutzt kein separates Ergebnis-Modul; das Search Engine Modul macht beides
- Doppelte Logik für Listen-Darstellung (Listen-Modul vs. Suchergebnis-Modul)
- Höherer Wartungsaufwand

---

## 4. Empfehlung: Ansatz A (mit Verfeinerungen)

### 4.1 Begründung

- **Contao-Konformität:** Entspricht dem etablierten Muster des Standard-Suchmoduls (Formular optional woanders, Weiterleitung auf Ergebnis-Seite).
- **Wiederverwendung:** Das Listen-Modul übernimmt die Ergebnis-Darstellung – keine Duplikation.
- **Flexibilität:** Suchformular kann im Header, in Sidebar oder auf eigener Seite stehen; Ergebnisse dort, wo das Listen-Modul eingebunden ist.
- **Blueprint:** Siehe CURSOR_BLUEPRINT.md 5.4 – explizit so vorgesehen.

### 4.2 Konkrete Umsetzung

#### Request-Parameter (Contao-konform)

| Parameter | Typ | Bedeutung |
|----------|-----|------------|
| `keywords` | string | Textsuche in Titel, Tags, Abstract |
| `zotero_author` | string\|int | **Standard:** `alias` aus `tl_member` (URL-freundlich, bookmarkbar). Alternativ: numerische `member_id` aus `tl_zotero_creator_map` |
| `zotero_year_from` | string | Erscheinungsjahr „von“ (4-stellig, validiert) |
| `zotero_year_to` | string | Erscheinungsjahr „bis“ (4-stellig, validiert) |

**`zotero_author`:** Das Such-Modul nutzt **standardmäßig den Member-Alias** (z. B. `zotero_author=mustermann`). Nur-Alpha-Numerische Aliase sind URL-tauglich; die Umwandlung `member_id` ↔ `alias` erfolgt im Listen-Modul. Direkte `member_id` wird ebenfalls akzeptiert (z. B. für Links aus dem Backend).

`keywords` bewusst gewählt – Übereinstimmung mit Contao Search Engine.

#### Such-Modul (ZoteroSearchController)

- **Typ:** `zotero_search`
- **Suchfeld:** Textfeld `keywords` – **keine Pflicht**. Der Besucher kann die Suche auch **ohne Eingabe** starten (nur Filter nutzen, z. B. „alle Publikationen von Autor X im Jahr 2020“).
- **Optionale Filter (Backend-gesteuert):** Im Backend muss angegeben werden, ob die Filter im Frontend angezeigt werden:
  - **Autor anzeigen** (Checkbox/Select): Wenn aktiv → Dropdown mit publizierten Mitgliedern, die Publikationen haben (tl_member, verknüpft über creator_map). Wert: Member-`alias` (Standard), Anzeige z. B. „Vorname Nachname“.
  - **Jahr anzeigen** (Checkbox/Select): Wenn aktiv → Zwei numerische Eingabefelder **„Von“** und **„Bis“** (jeweils 4-stellig, Client- und Server-seitig validiert). Wenn nur „Von“ befüllt → exakte Suche für dieses Jahr. Wenn nur „Bis“ befüllt → ebenfalls exakt dieses Jahr. Wenn beide → Bereich (BETWEEN). Ungültige Eingaben (nicht 4-stellig) werden zurückgewiesen.
- **Backend (Such-Modul):**
  - `zotero_libraries`, `zotero_search_page`, `zotero_search_show_author`, `zotero_search_show_year` (siehe oben)
  - **Suchkonfiguration:** Auswahl der zu durchsuchenden Felder mit **Reihenfolge/Priorität** (z. B. Titel=3, Tags=2, Abstract=1). Standard: Titel, Tags, Abstract; Reihenfolge bestimmt die Gewichtung im Token-Score.
  - **Token-Logik:** `zotero_search_token_mode` – AND (alle Tokens müssen vorkommen) oder OR (mindestens ein Token). Standard: AND.
  - **Max. Token-Anzahl:** Begrenzung der Token bei der Teilstring-Suche (z. B. 5–20, Standard 10). Schutz vor Performance-Problemen.
  - **Max. Trefferanzahl:** Limit der Suchergebnisse (z. B. 0=unbegrenzt, 50, 100, 500). Pagination greift danach.
- **Submit:** GET-Request auf `zotero_search_page`; mindestens ein Parameter muss gesetzt sein. Optional: **AJAX-Nachladen** bzw. **Live-Aktualisierung** der Ergebnisliste (ohne vollständigen Seiten-Reload) – als optionale Backend-Option.

#### Listen-Modul (ZoteroListController) – Erweiterung

- **Neue Backend-Option:** `zotero_search_module` (optional, Referenz auf ein Zotero-Such-Modul)
- **Logik:**
  1. Prüfen, ob `keywords`, `zotero_author`, `zotero_year_from`, `zotero_year_to` oder `zotero_item_type` gesetzt sind.
  2. **Ja (Suchmodus):** Libraries aus dem referenzierten Such-Modul (falls `zotero_search_module` gesetzt) oder aus eigener Konfiguration; `fetchItems()` mit erweiterten Filtern aufrufen.
  3. **Nein (Listenmodus):** Bisheriges Verhalten (Libraries/Collections aus Modul-Konfiguration).

#### Library-Konflikt: Such-Modul vs. Listen-Modul

**Frage:** Das Such-Modul hat Library X ausgewählt; das Listen-Modul (mit Referenz auf dieses Such-Modul) hat nur Library Y und Z. Welche Einschränkung gilt?

**Empfehlung: Schnittmenge (Intersection)**

- **Durchsucht und angezeigt werden** nur Items aus Libraries, die **sowohl** im Such-Modul **als auch** im Listen-Modul ausgewählt sind.
- Begründung: Das Listen-Modul definiert, welche Libraries auf der Ergebnis-Seite sichtbar sein dürfen (z. B. nur Projekt-Bibliotheken). Das Such-Modul definiert, in welchem Pool gesucht wird. Die striktere Einschränkung ist die des Listen-Moduls – es soll auf seiner Seite nichts anzeigen, was es nicht konfiguriert hat. Gleichzeitig sollen nur durchsucht werden, was im Such-Modul steht. **Schnittmenge** erfüllt beides.
- Alternative (nur Such-Modul zählt): Würde dazu führen, dass auf einer „nur Projekt Y/Z“-Seite plötzlich Treffer aus Library X erscheinen – inkonsistent mit der Seiten-Konfiguration.

#### Item-Typen: Such-Modul vs. Listen-Modul (analog Libraries)

**Schnittmenge (Intersection)** – gleiche Logik wie bei Libraries:

- Wenn das Listen-Modul **Item-Typen** einschränkt (`zotero_item_types` nicht leer), gelten im Suchmodus nur diese Typen.
- Form-Filter „Item-Typ“ schränkt zusätzlich ein: nur Typen, die sowohl im Listen-Modul erlaubt als auch im Formular gewählt sind. Beispiel: Listen-Modul erlaubt Buch + Zeitschriftenartikel; Form wählt „Buch“ → nur Bücher. Form wählt „Konferenzbeitrag“ (nicht in Listen-Konfiguration) → keine Treffer.
- Ohne Listen-Einschränkung: Form-Filter allein bestimmt (wie bisher).

#### Layout: Suchformular und Ergebnisliste auf einer Seite

**Unterstützt:** Ja – Such- und Listen-Modul können auf **derselben Seite** stehen. Typischer Ablauf:

1. **Erstbesuch** (ohne GET-Parameter): Der Besucher sieht nur das Suchformular oben. Das Listen-Modul zeigt die normale Liste (Libraries/Collections) oder bleibt leer, je nach Konfiguration.
2. **Nach Absenden der Suche:** Das Formular leitet auf dieselbe Seite weiter (GET mit `keywords`, `zotero_author`, `zotero_year_from`/`to`). Das Suchformular bleibt **oben** sichtbar (ggf. mit übernommenen Werten aus der URL), darunter erscheint die **Ergebnisliste**.
3. **Weitersuchen:** Der Benutzer kann Suchparameter anpassen und erneut absenden – alles auf derselben Seite.

**Technische Umsetzung:** Als **Weiterleitungsseite** (`zotero_search_page`) die **dieselbe** Seite auswählen, auf der Such- und Listen-Modul eingebunden sind. Das Formular submittet per GET auf diese URL; der Besucher bleibt auf der Seite, nur die Parameter ändern sich.

**Vorteile:** Bekannte UX (wie viele Such-Interfaces), keine Seitenwechsel, schnelle Anpassung der Filter. Contao-konform – entspricht dem Standard-Suchmodul mit Formular + Ergebnisse auf einer Seite.

#### Datenfluss

```
[Such-Modul] → Formular submit (GET) → [Weiterleitungsseite mit Listen-Modul]
                                           ↓
                                    ZoteroListController liest keywords, zotero_author, zotero_year_from/to
                                           ↓
                                    fetchItems() mit zusätzlichen WHERE-Bedingungen
                                           ↓
                                    Gleiche Template-Ausgabe wie im Listenmodus
```

#### Such-Logik in `fetchItems()` – Mehrstufige Volltextsuche

Die Suche nach `keywords` erfolgt in **drei Stufen** (Priorität absteigend), jeweils **case-insensitive**:

| Stufe | Beschreibung | Technik |
|-------|--------------|---------|
| **1** | Exakte Phrase im Titel | `LOWER(title) LIKE LOWER('%keyword phrasen%')` – höchste Priorität |
| **2** | Einzelner Suchbegriff im Titel | `LOWER(title) LIKE LOWER('%keyword%')` – für einen Begriff ohne Leerzeichen |
| **3** | Token-Suche über mehrere Felder | Suchbegriff wird in Tokens zerlegt; Vergleich mit **AND** oder **OR** (Backend-Option). Gewichtung: Titel > Tags > Abstract. Stop-Wörter werden ignoriert. Case-insensitive. Max. Token-Anzahl begrenzt (Backend-Einstellung). |

**Token-Suche (Stufe 3):**

- Nutzereingabe z. B. `"Agrarökologie Klimawandel"` → Tokens: `["agrarökologie", "klimawandel"]` (lowercase, Stop-Wörter entfernt).
- **AND-Modus:** Alle Tokens müssen in mindestens einem Feld vorkommen.
- **OR-Modus:** Mindestens ein Token muss vorkommen; Ranking nach gewichteter Trefferanzahl.
- **Suchbare Felder und Gewichtung** werden im Backend des Such-Moduls konfiguriert (inkl. Reihenfolge = Priorität).
- **Max. Token-Anzahl:** Backend-Limit (z. B. 10); überschüssige Tokens werden verworfen.

**Weitere Filter (unverändert):**

- **zotero_author:** Numerisch (member_id) oder Alias → JOIN über `tl_zotero_item_creator`, `tl_zotero_creator_map`, ggf. `tl_member`.
- **zotero_year_from / zotero_year_to:** `year BETWEEN from AND to` (ggf. mit VARCHAR-Vergleich).

#### Stop-Wörter (Stopwords)

- **Quellen:** [stopwords-iso/stopwords-de](https://github.com/stopwords-iso/stopwords-de) und [stopwords-iso/stopwords-en](https://github.com/stopwords-iso/stopwords-en) (MIT-Lizenz, Copyright Gene Diaz).
- **Ablegeort:** Im Bundle unter `Resources/stopwords/stopwords-de.php` und `Resources/stopwords/stopwords-en.php` als PHP-Arrays (`return [...];`).
- **Updatesichere Überschreibung:** Benutzer können eigene Dateien im Projekt ablegen (z. B. `config/zotero_stopwords_de.php`, `config/zotero_stopwords_en.php`). Der Service lädt zuerst die Projekt-Datei; falls nicht vorhanden, wird die Bundle-Datei verwendet.
- **Lizenzhinweis:** In jeder Stopword-Datei im Bundle wird die MIT-Lizenz und die Quelle (Gene Diaz, stopwords-iso) als Kommentar vermerkt.

#### Beispiel-SQL für Token-Suche (Stufe 3, AND-Modus)

Annahme: Suchbegriff `"Agrarökologie Klimawandel"`, Tokens nach Stopword-Filter: `agrarökologie`, `klimawandel`. Gewichtung: title=3, tags=2, abstract=1. `tags` ist JSON (z. B. `[{"tag":"Agrarökologie"}]`), daher genügt `LIKE` auf den Roh-JSON; Abstract steckt in `json_data.abstractNote`.

**AND-Modus:**

```sql
SELECT z.id, z.title, z.alias, z.year,
       (
         (CASE WHEN LOWER(z.title) LIKE '%agrarökologie%' THEN 3 ELSE 0 END) +
         (CASE WHEN LOWER(z.title) LIKE '%klimawandel%' THEN 3 ELSE 0 END) +
         (CASE WHEN LOWER(z.tags) LIKE '%agrarökologie%' THEN 2 ELSE 0 END) +
         (CASE WHEN LOWER(z.tags) LIKE '%klimawandel%' THEN 2 ELSE 0 END) +
         (CASE WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(z.json_data, '$.abstractNote'))) LIKE '%agrarökologie%' THEN 1 ELSE 0 END) +
         (CASE WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(z.json_data, '$.abstractNote'))) LIKE '%klimawandel%' THEN 1 ELSE 0 END)
       ) AS score
FROM tl_zotero_item z
WHERE (
  LOWER(z.title) LIKE '%agrarökologie%' OR LOWER(z.tags) LIKE '%agrarökologie%' OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(z.json_data, '$.abstractNote'))) LIKE '%agrarökologie%'
)
AND (
  LOWER(z.title) LIKE '%klimawandel%' OR LOWER(z.tags) LIKE '%klimawandel%' OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(z.json_data, '$.abstractNote'))) LIKE '%klimawandel%'
)
ORDER BY score DESC
LIMIT 100;
```

**OR-Modus:** Die `AND`-Gruppen zwischen den Tokens werden durch `OR` ersetzt; die Sortierung nach `score DESC` priorisiert Treffer mit mehr Token-Matches.

#### MySQL FULLTEXT vs. LIKE-basierte Suche

| Kriterium | MySQL FULLTEXT | LIKE-basierte Token-Suche |
|-----------|----------------|---------------------------|
| **Performance** | Sehr gut bei großen Datenmengen; eigener Index | O(n) pro LIKE; bei wenigen tausend Items meist akzeptabel |
| **Relevanz-Score** | MATCH() AGAINST() liefert nativen Score | Eigenes Gewichtungsmodell (Titel > Tags > Abstract) |
| **Stopwords** | MySQL-eigene Stopword-Liste | Eigene Listen (DE/EN), projektspezifisch überschreibbar |
| **Mindestwortlänge** | Default 3–4 Zeichen (innodb_ft_min_token_size) | Frei konfigurierbar |
| **JSON-Spalten** | Nicht direkt; Hilfsspalten nötig | JSON_EXTRACT + LIKE möglich |
| **Konfigurierbarkeit** | Festgelegt durch MySQL/InnoDB | Vollständig im Bundle steuerbar |
| **Abhängigkeiten** | InnoDB/MyISAM FULLTEXT-Index | Keine Schema-Erweiterung nötig |

**Empfehlung:** Für die **erste Implementierung** die **LIKE-basierte Token-Suche** nutzen:

- Keine zusätzlichen Indizes oder Schema-Migrationen.
- Volle Kontrolle über Stopwords, Gewichtung und Felder.
- Bei typischen Zotero-Bibliotheken (< 10.000 Items) meist ausreichend performant.

**Später optional:** Bei sehr großen Bibliotheken und Performance-Problemen kann ein FULLTEXT-Ansatz evaluiert werden (Hilfsspalten für Titel, Tags, Abstract; `FULLTEXT`-Index; `MATCH() AGAINST()`). Das würde eine Migrations- und Konzept-Anpassung erfordern.

#### AJAX / Live-Aktualisierung (optional)

- **Idee:** Bei Eingabe im Suchfeld werden Ergebnisse per AJAX nachgeladen bzw. die Liste live aktualisiert, ohne vollständigen Seiten-Reload.
- **Backend-Option:** z. B. `zotero_search_ajax` (Checkbox). Wenn aktiv: Formular-Submit verhindern; stattdessen XHR/Fetch auf eine Route, die JSON mit Items zurückgibt; Frontend rendert die Liste dynamisch.
- **Voraussetzung:** Eine dedizierte Route (z. B. `/zotero/search.json`) oder ein vorhandener Endpoint, der die gleiche Abfrage-Logik nutzt und JSON zurückgibt.
- **Nutzen:** Bessere UX, weniger Ladezeit beim erneuten Suchen. Kann in einer späteren Phase ergänzt werden.

#### Contao „Fuzzy“-Suche und Loupe

**Contao kann Loupe nutzen:** Contao unterstützt die [SEAL](https://github.com/schranz-search/seal)-Such-Abstraktion mit verschiedenen Backends. Über `cmsig/seal-loupe-adapter` wird **Loupe** ([loupe-php/loupe](https://github.com/loupe-php/loupe)) als Such-Engine angeboten – eine rein PHP/SQLite-basierte Volltextsuche.

**Loupe unterstützt Fuzzy-Matching (Typo Tolerance):**

- **Typo Tolerance:** Loupe nutzt einen „State Set Index“ für Tippfehler-Toleranz – Suchbegriffe mit kleinen Abweichungen finden passende Treffer.
- **Prefix Search:** Teilmatch (z. B. „huck“ findet „huckleberry“).
- **Tokenization, Stemming, Ranking** nach Relevanz.

**Legacy Contao-Suchmodul (ohne Loupe/SEAL):** Die klassische Option **„Fuzzy search“** (`tl_module.fuzzy`) entspricht lediglich einer **Wildcard-Suche** (Teilmatch), nicht Loupe-Typo-Tolerance.

**Konsequenz für Zotero-Such-Modul:** Das Zotero-Modul durchsucht **direkt die Datenbank** (`tl_zotero_item`), nicht den Contao-Suchindex. Es nutzt daher weder Loupe noch den Website-weiten Index. Für unsere DB-Suche bleibt:

- **Einfache Umsetzung:** `LIKE` mit optionalem Wildcard/Teilmatch (`%keyword%`) – analog zur Legacy-Contao-Option.
- **Loupe-Integration (evtl. Phase 5):** Falls Zotero-Items in den Contao-Suchindex (und ggf. Loupe) aufgenommen werden, profitiert die **Website-weite Suche** (Standard-Suchmodul) von Loupes Fuzzy-Matching. Das Zotero-Frontend-Such-Modul würde weiterhin die DB durchsuchen – für typo-tolerantere Zotero-interne Suchen müsste man einen Loupe-Index speziell für Zotero aufbauen (erhöhter Aufwand).

---

### 4.3 Alternative: Keine Modul-Referenz

Falls die Referenz `zotero_search_module` als zu komplex empfunden wird:

- **Such-Modul** speichert `zotero_libraries` (durchsuchbare Libraries).
- **Listen-Modul** hat Option „Als Suchergebnis-Seite nutzen“ – wenn aktiv und Such-Parameter vorhanden, werden die Libraries aus einem **auf derselben Seite** befindlichen Such-Modul gelesen (Seiten-Scan) oder aus einer eigenen Feldgruppe „Such-Konfiguration“.

- **Einfachere Variante:** Listen-Modul hat **eigene** Feldgruppe für Suchmodus (Libraries für Suche) – dann können Such- und Listen-Libraries unterschiedlich sein, was fachlich sinnvoll sein kann (z. B. Suche in allen Libraries, Anzeige nur bestimmte).

---

## 5. Contao-Suchindex (Phase 5) – Contao-konforme Umsetzung

### 5.1 Grundprinzip: Seiten-URLs indexieren

Contao indexiert **Seiten-URLs**. Wenn eine URL aufgerufen wird (Besuch oder Crawler), wird der HTML-Response durch den `SearchIndexListener` bzw. den Crawler in den Suchindex geschrieben. Das bedeutet: **Jede Zotero-Item-Detailseite (Lese-Modul)** sollte als URL verfügbar sein – dann wird sie bei Aufruf automatisch indexiert.

### 5.2 Contao-like Vorgehen: Sitemap-Event

**Empfehlung:** Ja – alle Zotero-Items, für die es eine Detailseite gibt (Lesemodul mit Reader-URL), sollten **ohnehin** im Suchindex landen. Das entspricht dem Contao-Standard:

- **News, Calendar, FAQ** fügen ihre Detail-URLs über das Event **`contao.sitemap`** zur Sitemap hinzu.
- Der Crawler (`contao:crawl` oder Backend „Suchindex aktualisieren“) ruft diese URLs auf.
- Der Standard-`SearchIndexListener` indexiert dabei den HTML-Inhalt jeder aufgerufenen Seite.

**Umsetzung für Zotero:**

1. **SitemapListener:** Eigenen Listener für `ContaoCoreEvents::SITEMAP` registrieren.
2. **URLs ermitteln:** Für jede Seite mit Lesemodul und jeder publizierten Library mit Reader-URL-Konfiguration: alle publizierten Zotero-Items durchlaufen und deren Detail-URLs bilden (z. B. `PageModel::getFrontendUrl('/' . $item['alias'])`).
3. **URLs zur Sitemap hinzufügen:** `$event->addUrlToDefaultUrlSet($url)` für jede Detail-URL.
4. **Ergebnis:** Beim Crawl werden diese URLs aufgerufen; der Standard-Indexer indexiert den gerenderten HTML-Inhalt (Titel, cite_content, Metadaten etc.).

**Vorteil:** Kein eigener `IndexerInterface` nötig – der Core-Indexer übernimmt das. Zotero-Publikationen erscheinen in der **Website-weiten Suche** (Standard-Suchmodul), sobald ihre Detailseiten gecrawlt oder besucht wurden.

**Einschränkung:** Nur Items mit erreichbarer Detail-URL werden indexiert. Listen-Seiten ohne Einzel-Item-Links werden nicht als solche indexiert (was fachlich okay ist – die einzelnen Publikationen sind die Such-Ziele).

### 5.3 Dezidierter Zotero-Suchindex – Zwei Anforderungen

**Anforderung 1:** Das Zotero-Such-Modul soll **nur** Zotero-Items finden, keine anderen Website-Inhalte.

**Anforderung 2:** Die allgemeine Website-Suche (Standard-Suchmodul) soll Zotero-Publikationen **mit** einbeziehen.

#### Contao-üblicher Weg: Ein Index, gefilterte Abfragen

Contao verwendet **einen zentralen Suchindex** (`tl_search`, `tl_search_index`, `tl_search_term`). News-, Calendar- und FAQ-Bundles fügen ihre Detail-URLs zur Sitemap hinzu; beim Crawl werden sie indexiert. Es gibt **keinen** physisch getrennten „News-Index“ – alles landet in `tl_search`. Die Trennung erfolgt über **Seiten-IDs (pid)**:

- `Search::query($keywords, $or, $arrPages, $fuzzy, $minLength)` akzeptiert `$arrPages` = Liste von Seiten-IDs.
- Nur Einträge mit `pid IN ($arrPages)` werden durchsucht.
- Das Standard-Suchmodul nutzt die Option „Referenz-Seite“, um die Suche auf einen Seitenbereich zu begrenzen.

**Umsetzung für Zotero:**

| Ziel | Vorgehen |
|------|----------|
| **Zotero-Such-Modul nur Zotero** | `Search::query()` mit `$arrPages` = nur IDs der Seiten, die ein Zotero-Lese-Modul enthalten. Damit werden ausschließlich Zotero-Detailseiten durchsucht. |
| **Website-Suche inkl. Zotero** | Standard-Suchmodul ohne spezielle Einschränkung (oder mit Referenz-Seite) durchsucht den **gesamten** Index – Zotero-Einträge sind bereits enthalten, sobald deren URLs gecrawlt wurden. |

**Ergebnis:** Ein gemeinsamer Index, zwei „logische Sichten“:
- Zotero-Modul: Filter auf Reader-Seiten → nur Zotero
- Website-Suche: Kein Filter (bzw. Referenz-Seite) → alles inkl. Zotero

#### Prüfung: Externe Quellen in die Website-Suche integrieren?

**Frage:** Gibt es im Contao-Core oder via Bundles eine Möglichkeit, den Suchindex um externe Quellen (z. B. `tl_zotero_search`) zu erweitern, sodass die Website-Suche auch Zotero-Treffer liefert?

**Geprüfte Contao-Mechanismen:**

| Mechanismus | Ergebnis |
|-------------|----------|
| **customizeSearch-Hook** | Modifiziert nur `$pageIds` (welche Seiten durchsucht werden). Kann keine Ergebnisse aus einer zweiten Tabelle hinzufügen. |
| **indexPage-Hook** | Wird bei der Indexierung aufgerufen; modifiziert `$indexData` für **ein** Dokument. Kann keinen zusätzlichen Eintrag aus externer Quelle erstellen. |
| **IndexerInterface** | Schreibt in tl_search (DefaultIndexer) oder eigene Tabelle. `Search::query()` fragt jedoch **nur** `tl_search` ab – ein eigener Indexer mit eigener Tabelle wäre für die Website-Suche unsichtbar. |
| **Suchergebnis-Hook** | Es existiert **kein** Hook, der nach `Search::query()` die Ergebnisse erweitert oder externe Treffer hinzufügt. |

**Ergebnis Contao-Core:** Es gibt **keine** dokumentierte oder hook-basierte Möglichkeit, die Website-Suche um Treffer aus einer separaten Tabelle zu erweitern.

**Geprüfte Packagist-Bundles:**

| Bundle | Funktion | Externe Quellen? |
|--------|----------|------------------|
| **heimrichhannot/contao-search-bundle** | PDF-Suche, Keyword-Limit, Seitenfilter | PDF-Text wird in den **bestehenden** Seiteneintrag gemerged (indexPage), keine separaten externen Einträge |
| **trilobit-gmbh/contao-search-bundle** | Konfiguration (max Keywords, min/max Länge) | Keine Indexerweiterung |
| **terminal42/contao-seal** | SEAL-Integration (Loupe, Meilisearch etc.); Provider-Konzept | **Theoretisch:** Eigener „Zotero-Provider“ möglich. Ersetzt aber die Standard-Suche komplett (SEAL statt tl_search), ist „Work in progress“ |

**Fazit:** Für den Standard-Suchmechanismus (tl_search, Search::query()) existiert **keine** Erweiterung, die externe Tabellen in die Website-Suche einblendet. Die einzige Option wäre **terminal42/contao-seal** mit eigenem Zotero-Provider – das wäre ein Ersatz der gesamten Suche, kein Add-on.

#### Alternativen (nicht empfohlen)

- **Separate Tabelle `tl_zotero_search`:** Eigenes IndexerInterface könnte Zotero-Items in eine eigene Tabelle schreiben. Das Zotero-Modul würde dann nur dort suchen. **Nachteil:** Die Website-Suche nutzt `Search::query()` und damit nur `tl_search`. Wie oben geprüft: Es gibt keinen Hook, um Ergebnisse aus einer zweiten Tabelle hinzuzufügen.

- **Zotero-Modul weiter per DB-Suche:** Das aktuelle Konzept durchsucht `tl_zotero_item` direkt mit `LIKE`. Das ist „dediziert“, findet nur Zotero, nutzt aber **nicht** den Contao-Suchindex. Die Website-Suche würde Zotero nur finden, wenn Zotero-URLs per Sitemap indexiert werden – unabhängig davon, wie das Zotero-Modul sucht.

#### Empfehlung: Zwei Suchwege für das Zotero-Modul

1. **Variante A (indexbasiert):** Zotero-Modul nutzt `Search::query()` mit gefilterten `pid`. Voraussetzung: Zotero-URLs sind in der Sitemap und wurden gecrawlt. Vorteil: Einheitliche Suchlogik (Volltext, Fuzzy, Relevanz), Loupe-Profit falls aktiv.
2. **Variante B (DB-Suche):** Zotero-Modul sucht weiter per `LIKE` in `tl_zotero_item`. Vorteil: Sofort nutzbar, kein Crawl nötig. Nachteil: Keine Volltext-Features des Index.

**Pragmatisch:** Variante B für Phase 4 (schnell umsetzbar). Variante A als Ausbau in Phase 5, wenn der Sitemap-Index steht und die Reader-Seiten-IDs bekannt sind.

**Technik Reader-Seiten-IDs:** Seiten mit Zotero-Lese-Modul ermitteln – z. B. über `tl_module` (type=zotero_reader) und die Zuordnung zu Seiten über Layout/Theme bzw. Artikel. Die Index-Einträge von Zotero-Detail-URLs erhalten beim Crawl die `pid` der jeweiligen Reader-Seite.

---

## 6. Zusammenfassung der Empfehlung

| Entscheidung | Empfehlung |
|--------------|------------|
| **Architektur** | Ansatz A: Getrenntes Such-Modul (nur Formular) + erweitertes Listen-Modul (zwei Modi) |
| **GET-Parameter** | `keywords`, `zotero_author` (alias oder member_id), `zotero_year_from`, `zotero_year_to` |
| **zotero_author** | Standard: Member-`alias` (URL-tauglich); Fallback: numerische `member_id` |
| **Jahr-Filter** | Zwei Felder Von/Bis, 4-stellig validiert; nur eines befüllt → exakt dieses Jahr |
| **Filter-Anzeige** | Backend steuert, ob Autor- und/oder Jahr-Filter im Frontend angezeigt werden |
| **Suche ohne keywords** | Besucher kann nur mit Filtern suchen (z. B. alle Publikationen von X im Jahr 2020) |
| **Library-Konflikt** | Schnittmenge: Nur Libraries, die sowohl im Such- als auch im Listen-Modul ausgewählt sind |
| **Fuzzy/Wildcard** | Loupe (falls genutzt) bietet Typo Tolerance. Zotero-DB-Suche: `LIKE` mit Teilmatch |
| **Contao-Suchindex** | Sitemap-Event: Detail-URLs zur Sitemap → Crawler indexiert. Ein Index, zwei Sichten: Zotero-Modul filtert auf Reader-Seiten; Website-Suche nutzt ganzen Index |
| **Weiterleitung** | Such-Modul → Weiterleitungsseite mit Listen-Modul (GET) |
| **Layout gleiche Seite** | Möglich: Suchformular oben, Ergebnisliste darunter; Weiterleitungsseite = dieselbe Seite |
| **Modul-Referenz** | Optional: `zotero_search_module` im Listen-Modul für Library-Übernahme im Suchmodus |
| **Suchfelder** | Titel, publication_title, tags (JSON), Abstract (json_data) |

---

## 7. Erweiterungskonzept Version 2 (Februar 2025)

**Status:** Entwurf – Umsetzung erst nach Freigabe.

### 7.1 Rollback: Gruppierung im Suchmodus

Die vorherige Änderung (Gruppierung auf Suchergebnisse anwenden) wird **zurückgebaut**. Sie unterläuft die Sortierung nach Relevanz-Score. Die betroffenen Änderungen sind in `ZoteroListContentController`, `ZoteroListController` und `change-log.md` dokumentiert und können per Git zurückgerollt werden.

---

### 7.2 Filter vs. Suche – Sortier- und Gruppierungslogik

| Situation | Verhalten |
|-----------|-----------|
| **Nur Filter** (kein Suchbegriff eingegeben) | Gruppierungs- und Sortiereinstellungen vom **Listen-CE/-Modul** werden verwendet. |
| **Suche mit Suchbegriff** | Abhängig von neuer Backend-Option im CE Suche/Filter: |
| → „Nach Gewicht sortieren“ aktiv | Sortierung nach Relevanz-Score (score DESC, title ASC). Listen-Sortierung und -Gruppierung werden ignoriert. |
| → „Nach Gewicht sortieren“ deaktiviert | Listen-Sortierung und -Gruppierung bleiben aktiv – auch bei Suchbegriff. |

**Bedingung für Gewichtssortierung:** Nur wenn **tatsächlich** ein Suchbegriff eingegeben wurde **und** die Gewichtung im Such-CE aktiviert ist, greift die Relevanz-Sortierung.

---

### 7.3 Durchsuchbare Felder – Erweiterung

**Neue Felder (zusätzlich zu title, tags, abstract):**

| Feld | Beschreibung | Datenquelle |
|------|--------------|-------------|
| **creators** | Autoren/Creators | `tl_zotero_creator_map.zotero_firstname`, `tl_zotero_creator_map.zotero_lastname` sowie verknüpfte `tl_member.firstname`, `tl_member.lastname` (falls member_id gesetzt) |
| **zotero_key** | Zotero-Item-Key | `tl_zotero_item.zotero_key` |
| **year** | Erscheinungsjahr | `tl_zotero_item.year` |
| **publication_title** | Publikationstitel (Zeitschrift etc.) | `tl_zotero_item.publication_title` |

**Abstract:** Wird als Spalte `abstract` (mediumtext NULL) in `tl_zotero_item` aufgenommen. ZoteroSyncService schreibt beim Sync den Inhalt aus `json_data.abstractNote` in die Spalte. Einheitliche Struktur für alle durchsuchbaren Felder außer Creators. **Migration:** Eine Einmal-Migration überführt `abstract` aus `json_data.abstractNote` in die neue Spalte für alle bestehenden Items.

**Tags:** Bleiben als JSON gespeichert, z. B. `[{"tag":"health","type":1},{"tag":"hyperketonemia","type":1},{"tag":"ketosis diagnosis","type":1}]`. Für die LIKE-Suche wird das Roh-JSON durchsucht (der Suchbegriff wird im String gefunden). Keine Umstellung.

**Indexe:** Keine zusätzlichen Indexe geplant. Die Suche erfolgt überwiegend mit LIKE (`%term%`); B-Tree-Indexe helfen bei Wildcard-Suche in der Mitte kaum. Bei typischen Zotero-Größen (< 10.000 Items) ausreichend performant.

---

### 7.4 Gewichtung pro Feld – Backend-Konfiguration

**Ersetzung des Textfelds „Durchsuchbare Felder“ durch 7 numerische Gewichtsfelder:**

| Feld | Default-Gewicht | Bedeutung |
|------|-----------------|-----------|
| zotero_search_weight_title | 100 | Titel |
| zotero_search_weight_creators | 10 | Creators (tl_zotero_creator_map + tl_member) |
| zotero_search_weight_tags | 10 | Tags |
| zotero_search_weight_publication_title | 1 | Publication Title |
| zotero_search_weight_year | 1 | Jahr |
| zotero_search_weight_abstract | 1 | Abstract |
| zotero_search_weight_zotero_key | 1 | Zotero-Key |

**Regel:** Gewicht `0` = Feld wird **nicht** durchsucht (deaktiviert).

**Backend:** Jedes Feld ein eigenes numerisches Eingabefeld (z. B. `eval: ['rgxp'=>'natural', 'minval'=>0]`). Reihenfolge im Formular entspricht der Tabelle; die numerische Gewichtung ersetzt die bisherige Reihenfolge als Priorität.

---

### 7.5 Suche optional – CE als reiner Filter

- **Backend-Option:** `zotero_search_enabled` (Checkbox). Wenn **nicht** aktiviert: CE fungiert als **reiner Filter** (Autor, Jahr, Item-Typ), kein Suchfeld im Frontend. **Standard: aktiviert** – bestehende CEs/Module behalten das Suchfeld.
- **Filter-Felder (Autor, Jahr, Item-Typ):** `zotero_search_show_author`, `zotero_search_show_year`, `zotero_search_show_item_type` – **Standard: deaktiviert**. Im Frontend erscheinen nur die Filter, die explizit im Backend aktiviert wurden.
- **Dynamische Paletten:** Die Such-Konfigurationsfelder (Gewichte, Token-Logik, Max. Tokens, Max. Treffer) werden nur angezeigt, wenn `zotero_search_enabled` aktiv ist (`__selector__` + `subpalettes`).
- **Umbenennung:** CE und Modul „Zotero-Suche“ → **„Zotero-Suche/Filter“** (Sprachdateien, DCA-Reference). CE und Modul haben dieselbe Konfiguration; Änderungen betreffen beide.

---

### 7.6 Sortier-Option im CE Suche/Filter

**Neue Backend-Option:** `zotero_search_sort_by_weight` (Checkbox, Standard: aktiv).

| zotero_search_sort_by_weight | keywords gesetzt | Verhalten |
|------------------------------|------------------|-----------|
| aktiviert | ja | Sortierung nach Relevanz-Score (Listen-Sortierung/Gruppierung ignoriert) |
| aktiviert | nein | Nur Filter → Listen-Sortierung/Gruppierung |
| deaktiviert | ja | Listen-Sortierung und -Gruppierung auch bei Suchbegriff |
| deaktiviert | nein | Nur Filter → Listen-Sortierung/Gruppierung |

**Kernlogik:** Wenn `keywords` leer ist, gilt **immer** die Listen-Sortierung und -Gruppierung. Die Option greift nur, wenn ein Suchbegriff eingegeben wurde.

**Wichtig:** Auch wenn die Sortierung nach Gewicht deaktiviert ist, bleiben die **Gewichtseinstellungen der durchsuchbaren Felder** wirksam: Felder mit Gewicht 0 werden gar nicht durchsucht. Die Gewichtung steuert also stets, welche Felder in die Suche einbezogen werden; `zotero_search_sort_by_weight` betrifft nur die **Sortierung** der Treffer, nicht die Auswahl der Suchfelder.

---

### 7.7 Token-Logik im Frontend wählbar

**Erweiterung von `zotero_search_token_mode`:**

| Wert | Bedeutung |
|------|-----------|
| `and` | AND (alle Begriffe) – fest im Backend |
| `or` | OR (mindestens ein Begriff) – fest im Backend |
| `frontend` | **Im Frontend wählbar** – der Nutzer wählt per Radio-Buttons |

**Backend:** Dropdown bleibt unverändert (3 Optionen: AND, OR, Frontend wählbar).

**Frontend:** Bei `frontend` – Radio-Buttons „Alle Begriffe / Mindestens ein Begriff“. GET-Parameter `query_type` (Werte: `and`, `or`). Bei `and`/`or` wird die Auswahl nicht angezeigt, der Wert ist fest.

---

### 7.8 Max. Token-Anzahl – Frontend-Hinweis

Wenn die eingegebene Suchphrase nach Tokenisierung **mehr** Tokens ergibt als `zotero_search_max_tokens` erlaubt:

- **Backend:** Tokens werden wie bisher auf das Limit gekürzt (Überschuss verworfen).
- **Frontend:** Zusätzlich eine **Info-Nachricht** anzeigen, z. B.: „Ihre Suche wurde auf X Begriffe gekürzt. Weitere Begriffe wurden ignoriert.“ Die Meldung erscheint **immer oberhalb der Trefferliste** – auch bei 0 Treffern –, damit der Nutzer die Kürzung bemerkt.

**Umsetzung:** Listen-Controller prüft, ob `count(tokenize(keywords)) > maxTokens`. Wenn ja, `template->token_limit_exceeded = true` und `template->token_limit = maxTokens` setzen. Im Listen-Template Hinweis-Box oberhalb der Ergebnisse rendern.

---

### 7.9 Creators-Suche – technische Umsetzung

Die Creators-Suche muss zwei Quellen abdecken:

1. **tl_zotero_creator_map:** `zotero_firstname`, `zotero_lastname` (pro Item über `tl_zotero_item_creator`).
2. **tl_member:** `firstname`, `lastname` (wenn `tl_zotero_creator_map.member_id` gesetzt).

**SQL-Strategie:** Subquery oder EXISTS-Klausel, die für jedes Item prüft, ob ein Token in einer der vier Spalten vorkommt:

```sql
-- Vereinfacht: Token "mueller" findet Items, bei denen
-- (cm.zotero_firstname LIKE '%mueller%' OR cm.zotero_lastname LIKE '%mueller%'
--  OR m.firstname LIKE '%mueller%' OR m.lastname LIKE '%mueller%')
-- für mindestens einen Creator des Items gilt.
```

**JOIN-Struktur:** `tl_zotero_item` → `tl_zotero_item_creator` → `tl_zotero_creator_map` [LEFT JOIN `tl_member` ON member_id]. Die LIKE-Bedingungen werden in die WHERE-Klausel integriert. Bei mehreren Tokens (AND/OR) je nach Modus verknüpfen.

---

### 7.10 Zusammenfassung der Änderungen (Version 2)

| Bereich | Änderung |
|---------|----------|
| **Rollback** | Gruppierung im Suchmodus entfernen |
| **Filter vs. Suche** | Kein keywords → Listen-Sortierung/Gruppierung; mit keywords → abhängig von `zotero_search_sort_by_weight` |
| **Durchsuchbare Felder** | 7 Felder: title, creators, tags, publication_title, year, abstract, zotero_key |
| **Abstract-Spalte** | `tl_zotero_item.abstract` (mediumtext); ZoteroSync befüllt; Einmal-Migration für bestehende Items |
| **Tags** | JSON bleiben; LIKE-Suche auf Roh-JSON |
| **Indexe** | Keine zusätzlichen – LIKE-Suche, B-Tree hilft kaum |
| **Gewichtung** | 7 numerische Felder, 0 = deaktiviert; **immer** wirksam (auch wenn Sortierung nach Gewicht aus) |
| **CE als Filter** | `zotero_search_enabled` (Default: aktiv); Filter-Felder Autor/Jahr/Item-Typ (Default: deaktiviert) |
| **Umbenennung** | „Zotero-Suche“ → „Zotero-Suche/Filter“ |
| **Sortier-Option** | `zotero_search_sort_by_weight` – bei deaktiviert: Listen-Sortierung/Gruppierung auch bei Suche; Feldgewichte bleiben aktiv |
| **Token-Logik** | Backend: Dropdown (AND/OR/frontend); Frontend bei `frontend`: Radio-Buttons |
| **Token-Limit** | Frontend-Hinweis oberhalb der Liste (auch bei 0 Treffern), wenn Begriffe gekürzt wurden |

---

## 8. Referenzen

- `schema-org-json-ld-konzept.md` – Schema.org/JSON-LD für Publikationen (optional: ItemCollection in Suchergebnissen)
- [Contao: Website search](https://docs.contao.org/manual/en/layout/module-management/website-search)
- [Contao: Create a search form](https://docs.contao.org/manual/en/form-generator/create-a-search-form)
- [Contao: Search Indexing](https://docs.contao.org/dev/framework/search-indexing/)
- [Contao: Hooks customizeSearch](https://docs.contao.org/dev/reference/hooks/customizeSearch/), [indexPage](https://docs.contao.org/dev/reference/hooks/indexPage/)
- [Contao: Events – contao.sitemap](https://docs.contao.org/dev/reference/events/#contao-sitemap)
- [Loupe PHP – Search Engine](https://github.com/loupe-php/loupe) (typo tolerance, fuzzy, für SEAL/Contao optional)
- [terminal42/contao-seal](https://github.com/terminal42/contao-seal) (SEAL-Integration, Provider-Konzept)
- [heimrichhannot/contao-search-bundle](https://packagist.org/packages/heimrichhannot/contao-search-bundle) (PDF-Suche, keine externen Quellen)
- [MetaModels: Components / Filters](https://metamodels.readthedocs.io/en/latest/manual/component/filter.html)
- CURSOR_BLUEPRINT.md, Abschnitte 5.3, 5.4
- ZoteroListController.php (bestehende `fetchItems()`-Logik)
