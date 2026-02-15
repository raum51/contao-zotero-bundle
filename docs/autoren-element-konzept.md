# Zotero-Autorenelement – Konzept

**Stand:** Februar 2026  
**Zweck:** Konzept für das CE „Publikationen eines Mitglieds“ mit URL-basierter Adressierung **nur per Pfad** (`auto_item`). Empfohlenes Member-Bundle: **oveleon/contao-member-extension-bundle**.

---

## 1. Einordnung und Ziel

Das **Zotero-Autorenelement** zeigt die Publikationen eines Contao-Mitglieds (tl_member) an – gefiltert über `tl_zotero_item_creator` ↔ `tl_zotero_creator_map` ↔ `member_id`.

**Zwei Nutzungsszenarien:**

| Szenario | Beschreibung |
|----------|--------------|
| **Feste Auswahl** | Ein Mitglied wird im Backend fest gewählt. Ersetzt die Zotero-Liste mit zotero_author-Filter. |
| **URL-basiert** | Das Mitglied wird aus der URL ermittelt – für **Detailseiten je Mitglied**, auf denen Publikationen angezeigt werden. |

Das URL-basierte Szenario setzt **Member-Detailseiten per Pfad** voraus. Das Bundle empfiehlt den Einsatz von **oveleon/contao-member-extension-bundle**, das genau diese Struktur bereitstellt (`/{jumpTo}/{alias}` bzw. `/{jumpTo}/{id}`).

**Ziel:** Adressierung ausschließlich per **Pfad** (Contao `auto_item`) – keine Query-Parameter.

---

## 2. Recherche: Member-Detailseiten in Contao-Bundles

### 2.1 Übersicht

| Bundle | Adressierung | Pfad | Query-Parameter | Contao 5 | Anmerkung |
|--------|--------------|------|-----------------|----------|-----------|
| **contao-memberlist** (friends-of-contao) | Query | – | `?show=<id>` | **Nein** (^4.4) | Letzte Version 2.0.8 (Juni 2024). Parameter `show` fest. List + Detail. |
| **oveleon/contao-member-extension-bundle** | Pfad | `/{jumpTo}/{alias oder id}` | – | Ja | Nutzt `auto_item`. MemberList + MemberReader. |
| **heimrichhannot/contao-member-listing-bundle** | – | – | – | Ja | Nur CE „Member list“ mit Backend-Member-Picker. Keine URL-/Detail-Adressierung. |
| **codefog/contao-member_content** | – | – | – | – | Content pro Mitglied; Anzeige nur für eingeloggten User (Session). Keine öffentlichen Detailseiten. |

### 2.2 contao-memberlist (friends-of-contao/contao-memberlist)

- **URL:** `?show=<member_id>` (nur ID, kein Alias)
- **Reader:** `ModuleMemberlist::compile()` prüft `Input::get('show')` → `listSingleMember(Input::get('show'))`
- **Template:** `mod_memberlist_detail`
- **Kein jumpTo:** List und Detail auf derselben Seite; Wechsel über Query-Parameter
- **Contao 5:** Nicht unterstützt (Composer: `^3.5 || ^4.4`). Für Contao-5-Projekte nicht nutzbar.

### 2.3 heimrichhannot/contao-member-listing-bundle

- **Kein URL-/Detail-Reader:** Nur CE „Member list“ mit Backend-Member-Picker.
- **Anzeige:** Mehrere Members werden per Picker ausgewählt und als Liste angezeigt.
- **Contao 5:** Unterstützt (^4.13 \|\| ^5.0). Keine Relevanz für Adressierung per URL oder Pfad.

### 2.4 oveleon/contao-member-extension-bundle

- **URL:** `{jumpTo-Seite}/{alias}` oder `{jumpTo-Seite}/{id}` – z. B. `/mitglieder/mustermann`
- **Reader:** `MemberReaderController` liest `Input::get('auto_item')` → `MemberModel::findByIdOrAlias($auto_item)`
- **Liste:** `generateMemberUrl()` baut Link: `$objPage->getFrontendUrl('/' . ($alias ?: $id))` (bei ext_memberAlias)
- **Kein Query-Parameter:** Ausschließlich Pfad über `auto_item`

#### 2.4.1 Recherche: tl_member – Alias und Adressierung

| Aspekt | Standard Contao | oveleon/contao-member-extension-bundle |
|--------|-----------------|--------------------------------------|
| **tl_member alias** | Kein Alias-Feld (im Gegensatz zu tl_article, tl_news, tl_page) | Bundle **erweitert** tl_member um Feld `alias` (DCA + Schema) |
| **URL-Generierung** | – | Modul-Einstellung `ext_memberAlias`: Wenn aktiv → `alias` oder Fallback `id`; sonst nur `id` |
| **Auflösung** | – | `MemberModel::findByIdOrAlias($auto_item)` – funktioniert für numerische ID und Alias-String |
| **Username** | – | Wird **nicht** für URLs verwendet |

**DCA-Erweiterung (oveleon):** In `contao/dca/tl_member.php` fügt oveleon das Feld `alias` zur tl_member-Palette hinzu (`rgxp=alias`, `unique`, `varchar(255)`). Ohne das Bundle hat tl_member dieses Feld nicht.

### 2.5 codefog/contao-member_content

- **Kein URL-basiertes Adressieren:** Content pro Mitglied; Anzeige nur für eingeloggten User
- **Kein Relevanz** für das Zotero-Autorenelement (öffentliche Mitgliedsseiten)

### 2.6 Contao Core: Modul „Auflistung“

- Kann `tl_member` abfragen und **Detail-Seiten-Felder** aktivieren
- Nutzt Contao-Standard-Routing; Detail-URLs über `auto_item` möglich

### 2.7 Kernaussage für das Autorenelement

- **Empfohlenes Bundle:** oveleon/contao-member-extension-bundle (Pfad `/mitglieder/alias` oder `/mitglieder/{id}`).
- **Adressierung:** Ausschließlich `Input::get('auto_item')` – kein Query-Parameter.

---

## 3. Adressierungs-Strategie: Nur Pfad

### 3.1 Pfad (`auto_item`)

Contao setzt das letzte URL-Segment als `auto_item` (z. B. `/mitglieder/mustermann` → `auto_item = mustermann`).

**Logik (Pseudocode):**

```php
$memberIdOrAlias = Input::get('auto_item');
```

### 3.2 Auflösung Member-ID vs. Alias

- `MemberModel::findByIdOrAlias($value)` stammt aus der Contao-Model-Basisklasse und existiert daher auch ohne oveleon.
- Numerischer Wert → ID-Suche (`findByPk`) – funktioniert **immer** (auch ohne alias-Spalte).
- Nicht-numerischer Wert → Alias-Suche (`findBy('alias', …)`) – **nur**, wenn tl_member eine Spalte `alias` hat (oveleon).

| Umgebung | Numerische ID | Alias |
|----------|---------------|-------|
| Nur Contao | ✓ | ✗ (SQL-Fehler: Spalte fehlt) |
| Mit oveleon | ✓ | ✓ |

---

## 4. Backend-Konfiguration

### 4.1 Modus (Selector)

| Modus | Bedeutung |
|-------|-----------|
| **fixed** | Mitglied fest im Backend gewählt (zotero_member) |
| **from_url** | Mitglied aus URL (Pfad) – für Member-Detailseiten |

### 4.2 Felder bei Modus `from_url`

| Feld | Typ | Beschreibung |
|------|-----|---------------|
| `zotero_libraries` | checkbox (hasMany) | Libraries für die Publikationssuche (Pflicht bei from_url) |

**Hinweis:** Die Adressierung erfolgt ausschließlich über `auto_item` (Pfad). Kein weiteres Konfigurationsfeld nötig.

### 4.3 Weitere Felder (beide Modi)

Analog zum Zotero-Listenelement:

- `zotero_template` – Darstellungsform (cite_content, json_dl, fields)
- `zotero_reader_element` – Optional: Referenz auf Zotero-Einzelelement (from_url) für News-Pattern
- `numberOfItems`, `perPage`, `zotero_list_order`, `zotero_list_sort_direction_date`, `zotero_list_group`
- Standard-CE-Felder: `title`, `protected`, `customTpl` usw.

### 4.4 Palette (Vorschlag)

```
{type_legend},title,type,headline;
{zotero_legend},zotero_member_mode,zotero_template;
  [Subpalette zotero_member_mode_fixed:] zotero_member
  [Subpalette zotero_member_mode_from_url:] zotero_libraries
{config_legend},numberOfItems,perPage,zotero_list_order,zotero_list_sort_direction_date,zotero_list_group;
{template_legend},zotero_reader_element;
{protected_legend},...
```

---

## 5. Technische Umsetzung

### 5.1 Controller: Member ermitteln

```php
// Nur Pfad (auto_item) – sichere Auflösung (ohne oveleon fehlt tl_member.alias)
$memberIdOrAlias = Input::get('auto_item');
$member = $memberIdOrAlias ? $this->findMemberByIdOrAlias($memberIdOrAlias) : null;
```

**Implementierung:** Wrapper `findMemberByIdOrAlias()`: numerisch → `findByPk`; nicht-numerisch → `findByIdOrAlias` im try-catch. Bei SQL-Fehler (Spalte `alias` fehlt) → `null`. So crasht das CE nicht, wenn auf derselben Seite ein Zotero-Einzelelement (Item-Reader) den `auto_item` belegt (z. B. Item-Alias) – Creator-Items zeigt leer.

### 5.2 Modus `fixed`

- `zotero_member` (pageTree oder Select auf tl_member) – ein Mitglied
- Kein URL-Check; Member direkt aus der Konfiguration

### 5.3 Modus `from_url`

- Ohne gültigen Member: **leere Ausgabe** (kein 404 – das CE zeigt einfach nichts)
- Optional: 404 werfen, wenn die Seite explizit eine Member-Detailseite ist und kein Member gefunden wurde (konfigurierbar?)

**Empfehlung:** Leere Ausgabe, da das CE auch auf Nicht-Member-Seiten stehen kann und dann keine Fehlermeldung erzeugen soll.

### 5.4 Filter-Logik (beide Modi)

- `authorMemberId` aus fixed oder aus URL
- Gleicher Code wie Zotero-Listenelement: `fetchItems(..., authorMemberId: $memberId)`
- Services: `ZoteroListController::fetchItems()` bzw. `ZoteroListContentController` – Filter bereits vorhanden (`zotero_author` → `authorMemberId`)

### 5.5 tl_member: Alias-Feld

**Standard Contao:** tl_member hat **kein** Alias-Feld.

**oveleon/contao-member-extension-bundle:** Erweitert tl_member um `alias`. Die gesamte Steuerung der Member-Seiten (URL-Generierung, List/Reader, Alias-Option) liegt bei oveleon. **Wir erweitern tl_member nicht** – wir bleiben kompatibel und unterstützen ID und Alias (wenn vorhanden).

---

## 6. Empfehlung und Kompatibilität

**Empfohlenes Member-Bundle:** oveleon/contao-member-extension-bundle (Member-Detailseiten per Pfad `/{jumpTo}/{alias}` oder `/{jumpTo}/{id}`).

**Entscheidung:** Die Erweiterung von tl_member (insb. Alias-Feld) überlassen wir vollständig dem Bundle oveleon/contao-member-extension-bundle. Wir unterstützen **ID und Alias** (falls vorhanden) über `MemberModel::findByIdOrAlias()`. Dadurch:
- Die gesamte Steuerung der Member-Seiten liegt bei oveleon
- Wir sind kompatibel mit und ohne oveleon (ohne oveleon funktioniert nur numerische ID)
- Keine redundante DCA-/Schema-Erweiterung in unserem Bundle

| Einsatzort | Modus | Konfiguration | Ergebnis |
|------------|-------|----------------|-----------|
| **oveleon** Member-Detailseite | from_url | `zotero_libraries` wählen | Liest `auto_item` (alias/id aus Pfad) ✓ |
| Artikel „Publikationen von Prof. X“ | fixed | `zotero_member` = Prof. X | Zeigt deren Publikationen ✓ |

---

## 7. Abgrenzung zum Zotero-Listenelement

| Aspekt | Zotero-Liste | Zotero-Autorenelement |
|--------|--------------|------------------------|
| Filter | Libraries, Collections, Item-Typen, optional Autor | Immer: ein Member (fixed oder from_url) |
| Autor-Filter | Optional (ein Mitglied aus Dropdown) | Kernfunktion – ohne Member keine Ausgabe |
| URL-basiert | Nein | Ja (Modus from_url) |
| Einsatz | Übersichtsseiten, Suchergebnisse | Member-Detailseiten, feste Autor-Seiten |

**Fazit:** Das Autorenelement ersetzt nicht die Liste, sondern ergänzt sie für den speziellen Fall „Publikationen eines Mitglieds“ – mit optionaler URL-Adressierung.

---

## 8. Zusammenfassung

- **Dualer Modus:** fixed (Backend-Auswahl) und from_url (URL-basiert)
- **Adressierung:** Ausschließlich **Pfad** (`auto_item`) – keine Query-Parameter
- **Empfohlenes Bundle:** oveleon/contao-member-extension-bundle für Member-Detailseiten

**Nächste Schritte:** Feldliste finalisieren, DCA anlegen, Controller implementieren, an ZoteroListContentController/fetchItems anbinden.
