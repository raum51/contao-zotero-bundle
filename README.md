
# raum51/contao-zotero-bundle

Contao 5 Bundle zum Importieren & Synchronisieren von Zotero-Libraries (User/Group) und zur Ausgabe im Frontend (Liste & Detail).

## Features (Skeleton)
- Verwaltung mehrerer **Zotero-Libraries** (User- oder Group-Library) im Backend (`tl_zotero_library`)
- Import & Sync Service (mit Platzhaltern für ETag/Last-Modified)
- Speicherung von Items und Collections in eigenen Tabellen (Caching)
- Frontend-Module: **Liste** & **Detail (Reader)**
- Symfony Console Command: `php bin/console raum51:zotero:sync`

## Installation (Entwicklung)
1. Dieses Repository lokal klonen, z. B. in `packages/contao-zotero-bundle` deines Contao-Projekts.
2. In deinem Hauptprojekt `composer.json` ergänzen:

```json
{
  "repositories": [
    { "type": "path", "url": "packages/contao-zotero-bundle" }
  ],
  "require": {
    "raum51/contao-zotero-bundle": "*@dev"
  }
}
```

3. `composer update`
4. Cache leeren: `php bin/console cache:clear`
5. Datenbank aktualisieren (Doctrine Schema): `php bin/console contao:migrate`

## Konfiguration
- Lege im Backend in **Zotero** > **Libraries** einen oder mehrere Einträge an (User/Group, ID, API Key).
- Führe einen Sync aus:
  - per Console `php bin/console raum51:zotero:sync`
  - oder implementiere später einen Backend-Button/Automatik (Cron).

## TODOs
- Vollständige Feldzuordnung (Mapping) zwischen Zotero-Items und `tl_zotero_item`
- Delta-Synchronisation mit ETag/If-None-Match & `If-Modified-Since`
- Erweiterte Filter (Autor, Jahr, Typ) im Listenmodul
- Detail-Template nach Wunsch anpassen

Lizenz: MIT
