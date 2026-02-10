# raum51/contao-zotero-bundle

Contao-Bundle zur Integration der Zotero API (Items, Gruppen, Benutzer-Bibliotheken, Collections) mit lokalem Caching, Autoren-Zuordnung zu Contao-Mitgliedern und Frontend-Ausgabe.

- **Ziel-Umgebung:** Contao 5.6+ / PHP 8.2+
- **Lizenz:** MIT

Siehe Projekt-Blueprint im Contao-Projekt-Root: `CURSOR_BLUEPRINT.md`

---

## Test-Command (ZoteroClient)

Der Command `contao:zotero:test-client` prüft die Verbindung zur Zotero API und den API-Key. Er dient außerdem dazu, die **Group-ID** für eine Zotero-Gruppenbibliothek zu ermitteln (für `tl_zotero_library` bei `library_type=group`).

**Geplante Verwendung:**

- **Key prüfen:** Einmaliger Aufruf mit deinem API-Key; bestätigt, dass Key und Client funktionieren.
- **Group-ID für tl_zotero_library:** Mit `--list-groups` alle Gruppen des Keys anzeigen; die ausgegebene Group-ID trägst du im Backend unter Literaturverwaltung → Library als **Library-ID** ein (und wählst **library_type = group**).

**Verwendung:**

```bash
php bin/console contao:zotero:test-client --api-key=DEIN_ZOTERO_API_KEY
```

- Ohne weitere Optionen wird die **Key-Validierung** aufgerufen (`GET /keys/{key}`). Die Antwort enthält u. a. die Zotero-User-ID und die Rechte des Keys.
- Einen API-Key erstellst du unter: [Zotero – API Keys](https://www.zotero.org/settings/keys).

**Gruppen auflisten (Group-ID für library_id):**

```bash
php bin/console contao:zotero:test-client --api-key=DEIN_KEY --list-groups
```

Zeigt alle Gruppen, auf die der Key Zugriff hat (Group-ID und Name). Die **Group-ID** kannst du in einer neuen Library unter Literaturverwaltung als **Library-ID** eintragen und **library_type** auf „group“ setzen.

**Optional – beliebiger API-Pfad:**

```bash
php bin/console contao:zotero:test-client --api-key=DEIN_KEY --path=/users/DEINE_USER_ID/items
```

`DEINE_USER_ID` steht in der Key-Antwort (erster Aufruf ohne `--path`). So kannst du z. B. einen Items-Abruf testen.
