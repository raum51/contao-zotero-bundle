# Zotero-Bundle: Strategie „Nur Content-Elemente“ (CE-only)

**Stand:** 14. Februar 2026  
**Zweck:** Zusammenfassung der Recherche, Diskussion und Konzept für einen CE-only-Ansatz (ohne Frontend-Module)

---

## 1. Recherche: Contao-Richtung CE vs. Module

### 1.1 Offizielle Contao-Entscheidung (Core Developers Meeting 2025)

Aus dem [Recap des ersten Contao Core Developers Meeting 2025](https://contao.org/en/news/recap-of-the-first-contao-core-developers-meeting-2025) (Februar 2025):

> *„Wir haben beschlossen, **keine neuen Frontend-Module mehr zu bauen** und sie **schrittweise abzuschaffen**. Stattdessen können Content-Elemente jetzt direkt ins Seitenlayout eingebettet werden. Am Ende werden **nur noch Content-Elemente** übrig bleiben, und die Nutzer entscheiden, wo sie sie einsetzen – Berechtigungen regeln den Rest.“*

> *„Das wird ab **Contao 5.6** möglich sein.“*

**Beispiele:** Login und Passkeys wurden bereits von Modulen zu Content-Elementen migriert.

### 1.2 Technische Umsetzung (Contao 5.6)

- **Slots im Layout:** Der `slot`-Twig-Tag ermöglicht es, CE (und vorerst noch Module) in Layout-Bereiche zu platzieren.
- **Layout-Template:** `reference.type == 'content_element' ? content_element(reference.id) : frontend_module(reference.id)` – CE und Module werden einheitlich behandelt.
- **CE in Artikeln und Layout:** CE können sowohl in Artikeln als auch im Seitenlayout platziert werden.

### 1.3 Kernaussage für das Zotero-Bundle

| Aspekt | Schlussfolgerung |
|--------|------------------|
| Content-Elemente obsolet? | **Nein** – CE sind die zukunftssichere Variante |
| Neue Frontend-Module bauen? | **Nein** – Contao plant deren Ausphasierung |
| Geplante Zotero-CE weiterverfolgen? | **Ja** – sie entsprechen der Contao-Richtung |

---

## 2. Zusammenspiel Liste ↔ Reader ↔ Suche (bleibt gleich)

### 2.1 Technische Kontinuität

Das Zusammenspiel funktioniert mit reinen CE genauso wie mit Modulen:

| Konzept | Heute (Module) | Später (nur CE) |
|---------|----------------|-----------------|
| Liste referenziert Reader | `zotero_reader_module` (ID aus tl_module) | `zotero_reader_element` (ID aus tl_content) |
| Reader rendern | `{{ frontend_module(reader_module_id) }}` | `{{ content_element(reader_element_id) }}` |
| Such-Formular → Listen-Ausgabe | GET-Parameter (keywords, zotero_author, zotero_year) | Unverändert; keine Referenz nötig |

### 2.2 Bestehende Referenz-Muster im Core

- **CE „Module“:** CE, das ein Modul aus `tl_module` referenziert („Modul in Artikel einbinden“).
- **CE „Content element“:** CE, das ein anderes CE referenziert (Alias).
- **News-Bundle:** Noch Module (Newslist, Newsreader); Migration zu CE ist vorgesehen, aber noch nicht umgesetzt.

**Fazit:** Die Logik bleibt identisch; nur die Datenquelle wechselt von `tl_module` auf `tl_content`.

---

## 3. Vorschlag: CE-only-Strategie für das Zotero-Bundle

Ziel: **Von Anfang an nur CE einsetzen**, Frontend-Module einstampfen, um Code-Duplikation zu vermeiden und der Contao-Richtung zu folgen.

### 3.1 CE 1: Zotero-Einzelelement

**Funktion:** Anzeige eines einzelnen Zotero-Items.

**Dualer Modus (universell als Reader einsetzbar):**

| Modus | Backend-Einstellung | Verhalten |
|-------|--------------------|-----------|
| **Fix** | Ein fest gewähltes Zotero-Item | Zeigt immer dieses eine Item (z. B. in Artikeln, Sidebar) |
| **URL-basiert** | Item aus URL lesen | Liest `auto_item` aus der URL und rendert das entsprechende Item – **= Reader** |

**Vorteil:** Ein CE deckt sowohl „feste Item-Anzeige“ als auch „Detailansicht/Reader“ ab. Kein separates Reader-Modul nötig.

**Backend-Felder (Vorschlag):**
- Modus: `fixed` | `from_url`
- Bei `fixed`: Item-Auswahl (tl_zotero_item)
- Bei `from_url`: zotero_overview_page, zotero_overview_label (Zurück-Link)
- Template-Auswahl, download_attachments, weitere Optionen

---

### 3.2 CE 2: Zotero-Listenelement

**Funktion:** Darstellung einer gefilterten Liste von Zotero-Items (analog zum aktuellen Listen-Modul).

**Backend-Einstellungen (vom aktuellen Modul übernehmbar):**
- Libraries (mehrfach)
- Collections-Filter
- Item-Typen-Filter
- Sortierung, Gruppierung
- Template-Auswahl
- Pagination
- **Referenz auf Zotero-Einzelelement** – für Reader-Modus (News-Pattern): bei `auto_item` in URL wird das referenzierte Einzelelement gerendert statt der Liste. **Nur Einzelelemente im Modus `from_url` dürfen referenziert werden.**

**Abdeckung:**
- Listen-Modul → ersetzt
- CE „Publikationen einer Collection“ → durch Filterset „nur diese Collection“ abgedeckt

---

### 3.3 CE 3: Zotero-Autorenelement

**Funktion:** Anzeige der Publikationen eines Contao-Mitglieds (tl_member).

**Dualer Modus:**
- **fixed:** Ein Mitglied im Backend fest gewählt
- **from_url:** Mitglied aus URL (Pfad `auto_item`) – für Member-Detailseiten

**Backend:** Modus, bei fixed: tl_member; bei from_url: Parametername (Default: `show`), Libraries. Sortierung, Gruppierung, Template wie Listenelement.

**Logik:** Filter nach `tl_zotero_item_creator` ↔ `tl_zotero_creator_map` ↔ `member_id`. Kann weitgehend die gleichen Services/Filter wie das Listenelement nutzen.

**Details:** Siehe [autoren-element-konzept.md](autoren-element-konzept.md) – empfohlen: oveleon/contao-member-extension-bundle, Adressierung nur per Pfad (`auto_item`).

**Abdeckung:**
- CE „Publikationen eines Members“ → ersetzt

---

### 3.4 CE 4: Zotero-Such element

**Funktion:** Suchformular (Keywords, Autor, Jahr) mit Weiterleitung auf Zielseite mit GET-Parametern.

**Backend:**
- Libraries für Suchbereich
- Weiterleitungsseite (jumpTo)
- Optionale Anpassungen am Formular

**Abdeckung:**
- Such-Modul → ersetzt

---

## 4. Überblick: Von Modulen zu CE

| Aktuell (Module) | Neu (CE) | Anmerkung |
|------------------|----------|-----------|
| Zotero-Listen-Modul | Zotero-Listenelement | Inkl. Collection-Filter |
| Zotero-Lese-Modul | Zotero-Einzelelement (Modus `from_url`) | Universelles Reader-CE |
| Zotero-Such-Modul | Zotero-Such element | Formular, Weiterleitung |
| CE Einzelnes Item | Zotero-Einzelelement (Modus `fixed`) | Ein Item; Logik = jetziges Lese-Modul |
| CE Publikationen Member | Zotero-Autorenelement | – |
| CE Publikationen Collection | Zotero-Listenelement | Über Collection-Filter |

---

## 5. Klärungen (Entscheidungen)

### 5.1 Einzelne fixe Items

**Entschieden:** Nur ein Item. Das Einzelelement entspricht in seiner Logik dem jetzigen Lese-Modul – kein Multi-Select, keine Mehrfach-Ausgabe.

### 5.2 Referenzrichtungen

**Listenelement → Einzelelement:** Das Listenelement darf nur Einzelelemente referenzieren, die im Modus `from_url` sind. So ist klar, dass es sich um Reader-CEs für die Detailansicht handelt.

### 5.3 Migration

**Entscheidung:** Keine Migration erforderlich. Das Bundle befindet sich derzeit nur in lokaler Entwicklung.

### 5.4 Feldnamen (overviewPage vs. zotero_overview_page)

**Entscheidung:** Wir bleiben bei den Bundle-spezifischen Feldnamen `zotero_overview_page` und `zotero_overview_label` statt der Contao-Modul-Konvention (`overviewPage`, `customLabel`).

**Begründung:** Contao hat für tl_content noch kein offizielles CE-Reader-Pattern; News/FAQ/Calendar nutzen weiterhin tl_module. Wenn Contao eine offizielle CE-only-Strategie umsetzt, ist unklar, welche Feldnamen gewählt werden. Bundle-spezifische Namen vermeiden zukünftige Konflikte mit Core-Feldern.

---

## 6. Einschätzung zum Vorschlag

### 6.1 Vorteile

- **Ein Modell:** Nur CE, keine parallele Modul-Implementierung
- **Contao-konform:** Entspricht der geplanten Contao-Architektur
- **Weniger Redundanz:** Einzelelement mit dualem Modus ersetzt Reader-Modul und festes Item-CE
- **Klare Trennung:** Listenelement (Filter), Autorenelement (Member), Einzelelement (Item/Reader), Suche (Formular)

### 6.2 Herausforderungen

- **Umbau:** Bestehende Modul-Controller, DCA, Templates müssen auf CE migriert werden
- **Liste + Reader:** Das Listenelement muss das Einzelelement (Modus `from_url`) referenzieren und bei `auto_item` anstatt der Liste rendern – technisch wie beim jetzigen Modul

### 6.3 Empfehlung

Der CE-only-Ansatz ist sinnvoll und konsistent mit der Contao-Strategie. Das **universelle Einzelelement** (fix vs. from_url) reduziert Duplikate und vereinfacht die Architektur.

**Umsetzungsreihenfolge (Stand 16.02.2026):**

1. [x] Zotero-Einzelelement (beide Modi)
2. [x] Zotero-Listenelement (mit Referenz auf Einzelelement)
3. [x] Zotero-Autorenelement (aufbauend auf Listenelement)
4. [x] Zotero-Such element
5. [x] Frontend-Module entfernen

---

## 7. Recherche: CE-Bundles im Contao-Umfeld (Februar 2026)

Überblick über Bundles, die auf CEs statt Frontend-Module setzen bzw. Reader-/Listen-Funktion als CE anbieten.

### 7.1 Offizielle Contao-Bundles

| Bundle | CE oder Modul | Stand |
|--------|----------------|-------|
| contao/news-bundle | Nur **Module** | newslist, newsreader, newsarchive, newsmenu |
| contao/faq-bundle | Nur **Module** | faqlist, faqreader, faqpage |
| contao/calendar-bundle | Nur **Module** | eventlist, eventreader, eventmenu |
| contao/listing-bundle | Gemischt | Allgemeines Listing; kein News/FAQ/Reader-Ersatz |

**Fazit:** Offizielle News-, FAQ- und Kalender-Bundles nutzen weiterhin Frontend-Module. Es gibt aktuell keine offizielle CE-only-Strategie als Ersatz.

### 7.2 Drittanbieter: Einzelnews als CE

| Bundle | Beschreibung |
|--------|--------------|
| [bright-cloud-studio/contao-ce-news](https://github.com/bright-cloud-studio/contao-ce-news) | CE zur Anzeige einzelner News-Artikel |
| [heimrichhannot/contao-newselement](https://github.com/heimrichhannot/contao-newselement) | Einzelnews als CE; Filter nach Archiv, Template-Auswahl |

### 7.3 Drittanbieter: Generische List-/Reader-Bundles (HeimrichHannot)

| Bundle | Beschreibung | CE oder Modul |
|--------|--------------|---------------|
| [heimrichhannot/contao-list-bundle](https://github.com/heimrichhannot/contao-list-bundle) | Generisches Listen-Modul für beliebige DCA-Entities | **Modul** |
| [heimrichhannot/contao-reader-bundle](https://github.com/heimrichhannot/contao-reader-bundle) | Generisches Reader-Modul für beliebige DCA-Entities | **Modul** |
| [heimrichhannot/contao-news-list-reader-bundle](https://github.com/heimrichhannot/contao-news-list-reader-bundle) | News-Adapter für list- und reader-bundle | Nutzt list/reader (Module) |

### 7.4 Einordnung des Zotero-Bundles

| Aspekt | Einschätzung |
|--------|--------------|
| CE-only (Liste + Reader) | Zotero-Bundle ist hier Vorreiter – vergleichbare Lösungen selten |
| Liste + Reader + Suche als CEs | Kein bekanntes offizielles oder Drittanbieter-Bundle mit vergleichbarem Umfang |
| Einzelitems als CE | Vergleichbar mit contao-ce-news / contao-newselement |
| Konventionen (overviewPage etc.) | Kein etabliertes CE-Pattern – tl_content hat diese Felder nicht; offizielle Reader existieren nur als Module |

---

## 8. Referenzen

- [Recap Contao Core Developers Meeting 2025](https://contao.org/en/news/recap-of-the-first-contao-core-developers-meeting-2025)
- [Contao Docs: Content Elements & Modules](https://docs.contao.org/dev/getting-started/content-elements-modules)
- [Contao Docs: slot-Tag (Contao 5.6)](https://docs.contao.org/dev/reference/twig/tags/slot)
- [Include elements (CE „Module“, CE „Content element“)](https://docs.contao.org/manual/en/article-management/content-elements/include-elements)
- Projekt: `reader-modul-vorschlag.md`, `such-modul-konzept.md`, `CURSOR_BLUEPRINT.md`
- CE-Bundle-Recherche: Abschnitt 7
