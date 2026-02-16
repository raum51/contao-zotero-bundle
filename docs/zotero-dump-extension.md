# ZoteroDumpExtension – Twig {% dump %} in allen Umgebungen

## Hinweis

Die **ZoteroDumpExtension** ist **nicht üblich** in Contao-Projekten. Sie wurde ergänzt, um den Twig-Tag `{% dump %}` auch in Produktion parsebar zu machen – standardmäßig ist `dump` nur in der Dev-Umgebung verfügbar. Das Bundle liefert den Code mit. **Für die v1-Veröffentlichung** soll die Extension deaktiviert sein (Service in `services.yaml` auskommentieren).

---

## Funktion

- Stellt den Twig-Tag `{% dump %}` **in allen Umgebungen** zur Verfügung.
- In **Dev**: Dumps erscheinen im Symfony Web Profiler (Target-Icon in der Debug-Toolbar).
- In **Prod**: Der Tag wird geparst, führt aber **nichts aus** – Symfony's DumpNode prüft intern `env->isDebug()`.

Ohne die Extension führt `{% dump %}` in Produktion zu: `Unknown "dump" tag`.

---

## Anleitung: Extension aktivieren

Wenn du beim Entwickeln den `{% dump %}`-Tag nutzen möchtest:

### 1. Service in services.yaml aktivieren

In `Resources/config/services.yaml` den Block **einbinden** (nicht auskommentiert):

```yaml
    Raum51\ContaoZoteroBundle\Twig\ZoteroDumpExtension:
        tags: [twig.extension]
```

### 2. Im Template verwenden

```twig
{% dump %}                                    {# Gesamter Kontext #}
{% dump item, download_attachments, item_template %}   {# Bestimmte Variablen #}
```

### 3. Cache leeren

```
rm -rf var/cache/prod
php bin/console cache:clear --no-warmup
php bin/console cache:warmup
```

---

## Anleitung: Extension deaktivieren (für v1 / Produktiv-Deployment)

### 1. Service in services.yaml auskommentieren

```yaml
#    Raum51\ContaoZoteroBundle\Twig\ZoteroDumpExtension:
#        tags: [twig.extension]
```

### 2. {% dump %} aus den Templates entfernen

Ohne aktive Extension führt `{% dump %}` zu einem Fehler. Alle Vorkommen von `{% dump %}` bzw. `{% dump … %}` aus den Zotero-Templates entfernen.

### 3. Code behalten

Die Datei `src/Twig/ZoteroDumpExtension.php` kann im Bundle bleiben – sie wird ohne Service-Registrierung nicht geladen.

---

## Geltungsbereich

Wenn die Extension aktiv ist, gilt der Tag **in allen Twig-Templates** der Anwendung (nicht nur in Zotero-Templates).

---

## Sicherheit

In Produktion werden keine Daten ausgegeben: `env->isDebug()` ist dort `false`, der Dump-Code wird nicht ausgeführt. Eine Aktivierung in Prod ist unkritisch, wird aber für die v1-Veröffentlichung nicht empfohlen.
