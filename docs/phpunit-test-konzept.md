# PHPUnit-Test-Konzept für das Contao Zotero Bundle

**Stand:** 14. Februar 2026  
**Zweck:** Grundlage für die Einführung von automatisierten Tests

**Zeitpunkt der Umsetzung:** Erst wenn das Bundle weitgehend fertig ist und nur noch der Feinschliff fehlt (Phase 5). Bis dahin dient dieses Dokument als Referenz.

**Hinweis:** Der Entwickler hat noch keine Erfahrung mit PHPUnit; die Umsetzung soll schrittweise und mit Erklärung erfolgen (z. B. beginnend bei ZoteroBibUtil als einfachstem Fall).

---

## 1. Contao-übliche Test-Strategien

### 1.1 contao/test-case (Standard)

Contao bietet das Paket **contao/test-case** als Basis für Bundle-Tests:

- **Installation:** `composer require --dev contao/test-case` (im Root der Contao-Installation oder im Bundle)
- **Basisklasse:** `Contao\TestCase\ContaoTestCase`
- **Typische Methoden:**
  - `getContainerWithContaoConfiguration(?string $projectDir)` – Symfony-Container mit Contao-Standard-Konfiguration
  - `mockContaoFramework(array $instances = [])` – Contao-Framework-Mock mit Config-Adapter

**Paket-Anforderungen (Stand 2024):**
- PHP ^8.1
- PHPUnit ^9.5 (ältere Versionen) bzw. ^10 oder ^11 (je nach Paket-Version)
- Symfony YAML ^6.4

### 1.2 Test-Kategorien im Contao-Ökosystem

| Kategorie | Beschreibung | Typisch für |
|-----------|--------------|-------------|
| **Unit Tests** | Isolierte Logik, Mocks für Abhängigkeiten | Services, Utils, reine Funktionen |
| **Integration Tests** | Reale/simulierte DB, Container, Symfony Kernel | DCA-Callbacks, Migrationen, komplexe Services |
| **Functional Tests** | Vollständiger Request/Response | Controller, Routen (oft mit WebTestCase) |

### 1.3 Typische Struktur (Contao-Bundles)

```
bundles/raum51/contao-zotero-bundle/   # relativ zur Contao-Installation (z.B. contao-zotero-bundle-v56.local/bundles/...)
├── src/
├── Tests/
│   ├── Unit/           # Isolierte Logik
│   ├── Integration/    # DB, Container
│   └── Functional/     # Optional: HTTP-Requests
├── phpunit.xml.dist
└── composer.json      # require-dev: contao/test-case, phpunit
```

Referenz: [contao/news-bundle](https://github.com/contao/news-bundle), [contao/newsletter-bundle](https://github.com/contao/newsletter-bundle) – beide nutzen `phpunit.xml.dist` und `Tests/`.

### 1.4 Mocking-Strategien

- **Symfony Container:** `ContaoTestCase::getContainerWithContaoConfiguration()`
- **Contao Framework / Models:** `ContaoTestCase::mockContaoFramework()` mit Instanzen
- **Doctrine DBAL Connection:** `Connection::createConnection()` mit In-Memory-SQLite oder Mock
- **HTTP-Client (Zotero API):** Mock-Responses via Symfony HttpClient Test-Transport oder eigene Fakes
- **KernelInterface:** Mock für `getProjectDir()` (ZoteroStopwordService)

---

## 2. Priorisierung: Was testen?

### 2.1 Hohe Priorität (einfach, hoher Nutzen)

| Komponente | Grund | Test-Art |
|------------|-------|----------|
| **ZoteroBibUtil** | Reine statische Funktionen, keine Abhängigkeiten | Unit |
| **ZoteroStopwordService** | Klare Logik, Kernel-Mock möglich | Unit |
| **ZoteroSearchService::tokenize()** | Private Methode → evtl. über search() oder extrahierbar testen | Unit/Integration |

### 2.2 Mittlere Priorität (wichtig, etwas Aufwand)

| Komponente | Grund | Test-Art |
|------------|-------|----------|
| **ZoteroSearchService** | Komplexe Suchlogik (Phrase, Token, Scoring) | Integration (DB-Mock oder SQLite) |
| **ZoteroLocaleLabelService** | DB-Abhängig, Labels korrekt? | Integration |
| **ZoteroBibUtil::sanitizeAlias** | Edge Cases (Sonderzeichen, Leerstring) | Unit |

### 2.3 Niedrigere Priorität (aufwändiger)

| Komponente | Grund | Test-Art |
|------------|-------|----------|
| **ZoteroClient** | HTTP, Retry, Backoff – braucht Mock-Transport | Integration |
| **Controller** | Request, Model, Response – viele Mocks | Integration/Functional |
| **Migrationen** | Schema-Änderungen – DB-Setup nötig | Integration |
| **DCA-Callbacks** | DataContainer-Kontext | Integration |

---

## 3. Konkrete Test-Fälle (Vorschlag)

### 3.1 ZoteroBibUtil (Unit)

```php
// Tests/Unit/Service/ZoteroBibUtilTest.php
```

| Methode | Test-Fall | Erwartung |
|---------|-----------|-----------|
| `extractCiteKeyFromBib()` | Leerer String | `''` |
| `extractCiteKeyFromBib()` | `@article{najm_2020, ...}` | `'najm_2020'` |
| `extractCiteKeyFromBib()` | `@book{ author="X", ...}` (Key mit Leerzeichen – ungültig) | Regex trifft nicht |
| `extractCiteKeyFromBib()` | Kein @-Block | `''` |
| `sanitizeAlias()` | `'najm-2020'` | `'najm-2020'` |
| `sanitizeAlias()` | `'najm 2020'` (Leerzeichen) | `'najm2020'` |
| `sanitizeAlias()` | `'Sonder:zeichen!'` | Nur alphanum, `_`, `-` |
| `sanitizeAlias()` | Leerstring | `''` |

### 3.2 ZoteroStopwordService (Unit)

| Methode | Test-Fall | Erwartung |
|---------|-----------|-----------|
| `getStopwords('de')` | Normale Locale | Array aus stopwords-de.php |
| `getStopwords('en')` | Normale Locale | Array aus stopwords-en.php |
| `getStopwords('fr')` | Nicht unterstützt | `[]` |
| `getStopwords('DE')` | Großschreibung | Normalisiert zu 'de' |
| `getStopwordsForLocale('de-DE')` | Locale mit Unterstrich | Extrahiert 'de', liefert de-Stopwords |
| Cache | Zweimal `getStopwords('de')` | Dieselbe Instanz, kein doppelter File-Load |

**Mock:** `KernelInterface::getProjectDir()` → Temp-Verzeichnis ohne Projekt-Stopwords, damit Bundle-Dateien geladen werden.

### 3.3 ZoteroSearchService (Integration)

**Option A – In-Memory-SQLite:** Schema von `tl_zotero_item`, `tl_zotero_item_creator`, `tl_zotero_creator_map` anlegen, Testdaten einfügen, `search()` aufrufen.

**Option B – QueryBuilder-Mock:** Nur die erzeugte SQL-Logik prüfen (aufwendiger).

**Empfohlen:** Option A für realistische Tests.

| Szenario | Parameter | Erwartung |
|----------|-----------|-----------|
| Leere Libraries | `libraryIds = []` | `[]` |
| Leere Item-Types | `itemTypes = []` | `[]` |
| Leere Keywords | `keywords = ''` | Alle Items (sortiert), Pagination |
| Autor-Filter | `authorMemberId = 5` | Nur Items mit Creator ↔ Member 5 |
| Jahr-Filter | `yearFrom=2020, yearTo=2022` | Nur Items mit year in [2020,2022] |
| Phrase-Suche | `keywords = 'zotero'` | Items mit 'zotero' in title/tags/abstract |
| Token-Suche | `keywords = 'zotero api'` | Items mit beiden Begriffen (AND/OR je tokenMode) |
| Stopwords | `keywords = 'der die das'` (DE) | Stopwords ignoriert, ggf. leere Treffer |

---

## 4. Technische Einrichtung

### 4.1 composer.json (Bundle)

```json
{
  "require-dev": {
    "contao/test-case": "^5.3",
    "phpunit/phpunit": "^10.0 || ^11.0"
  },
  "autoload-dev": {
    "psr-4": {
      "Raum51\\ContaoZoteroBundle\\Tests\\": "Tests/"
    }
  }
}
```

**Hinweis:** `contao/test-case` kann PHPUnit als Abhängigkeit mitbringen; prüfen, ob explizites `phpunit/phpunit` nötig ist.

### 4.2 phpunit.xml.dist (Bundle-Root)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="Tests/bootstrap.php"
         cacheDirectory=".phpunit.result.cache"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>Tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>Tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
        <exclude>
            <directory>src/Migration</directory>
        </exclude>
    </source>
</phpunit>
```

### 4.3 Bootstrap

Tests können vom **Root der Contao-Installation** aus laufen (dort ist der vollständige Contao-Container – bei Unterordner-Struktur z.B. `contao-zotero-bundle-v56.local`), oder das Bundle hat eine eigene `Tests/bootstrap.php` für isolierte Unit-Tests (nur Autoload, evtl. minimale Container-Mocks).

**Empfehlung:** 
- Unit-Tests (ZoteroBibUtil, ZoteroStopwordService): Eigenes Bootstrap mit Composer-Autoload
- Integration-Tests: Vom Root der Contao-Installation mit `php bin/phpunit -c bundles/raum51/contao-zotero-bundle/phpunit.xml.dist` – nutzt Projekt-Container und DB

---

## 5. Empfohlene Reihenfolge der Umsetzung

1. **phpunit.xml.dist + Bootstrap** – Grundgerüst
2. **ZoteroBibUtilTest** – Schnelle Erfolgserlebnisse, keine Abhängigkeiten
3. **ZoteroStopwordServiceTest** – Kernel-Mock, Dateisystem
4. **ZoteroSearchServiceTest** – Erster Integration-Test mit SQLite
5. Optional: **ZoteroLocaleLabelServiceTest**, **ZoteroClientTest**

---

## 6. CI/CD (optional)

- **GitHub Actions:** `composer install`, `php bin/phpunit` im Root der Contao-Installation mit Bundle-Pfad
- **Vor Commit:** `composer test` oder `./vendor/bin/phpunit` als Script in composer.json

---

## 7. Referenzen

- [contao/test-case auf Packagist](https://packagist.org/packages/contao/test-case)
- [Contao Developer Documentation](https://docs.contao.org/dev/)
- [PHPUnit Dokumentation](https://phpunit.de/documentation.html)
- Projekt: `content-elemente-strategie.md`, `such-modul-konzept.md`
