# Felder tl_zotero_item und Kind-Tabellen: Backend-Bearbeitbarkeit und Sync-Überschreibung

**Quelle:** DCA-Definitionen, ZoteroSyncService  
**Stand:** 2026-02-23

## tl_zotero_item

| Feld | Im Backend bearbeitbar? | Anpassungen beim erneuten Sync überschrieben? |
|------|-------------------------|-----------------------------------------------|
| id | Nein (automatisch) | – |
| pid | Nein (Relation) | Nein (bei Update ausgenommen) |
| tstamp | Nein | Ja |
| zotero_key | Nein (readonly) | Ja |
| alias | Nein (readonly) | Ja |
| zotero_version | Nein (readonly) | Ja |
| title | Nein (readonly) | Ja |
| item_type | Nein (readonly) | Ja |
| year | Nein (readonly) | Ja |
| date | Nein (readonly) | Ja |
| publication_title | Nein (readonly) | Ja |
| cite_content | Nein (readonly) | Ja |
| bib_content | Nein (readonly) | Ja |
| abstract | Nein (readonly) | Ja |
| json_data | Nein (readonly) | Ja |
| tags | Nein (readonly) | Ja |
| download_attachments | Ja (Toggle) | **Nein** – nur bei Insert auf 0 gesetzt |
| published | Ja (Toggle) | **Nein** – nur von Redaktion geändert |
| trash | Nein (readonly) | **Ja** – spiegelt Zotero-Papierkorb (deleted) |

**Hinweis trash:** Trennung von published (Contao-Veröffentlichung) und trash (Zotero-Papierkorb). trash wird ausschließlich vom Sync gesetzt. Frontend filtert: published=1 und trash=0.

---

## tl_zotero_item_creator (Kind von tl_zotero_item)

| Feld | Im Backend bearbeitbar? | Anpassungen beim erneuten Sync überschrieben? |
|------|-------------------------|-----------------------------------------------|
| id | Nein (automatisch) | – |
| pid | Nein (Relation) | Ja |
| tstamp | Nein | Ja |
| item_id | Nein (readonly) | Ja |
| creator_map_id | Nein (readonly) | Ja |
| sorting | Nein (readonly) | Ja |

**Hinweis:** Alle Felder readonly. Beim Sync werden Einträge gelöscht und aus Zotero neu angelegt.

---

## tl_zotero_item_attachment (Kind von tl_zotero_item)

| Feld | Im Backend bearbeitbar? | Anpassungen beim erneuten Sync überschrieben? |
|------|-------------------------|-----------------------------------------------|
| id | Nein (automatisch) | – |
| pid | Nein (Relation) | Nein (bei Update ausgenommen) |
| tstamp | Nein | Ja |
| sorting | Nein | Nein (bei Update ausgenommen) |
| zotero_key | Nein (readonly) | Ja |
| zotero_version | Nein (readonly) | Ja |
| link_mode | Nein (readonly) | Ja |
| title | Nein (readonly) | Ja |
| filename | Nein (readonly) | Ja |
| content_type | Nein (readonly) | Ja |
| url | Nein (readonly) | Ja |
| charset | Nein (readonly) | Ja |
| md5 | Nein (readonly) | Ja |
| json_data | Nein (readonly) | Ja |
| published | Nein (readonly) | **Nein** – nur bei Insert auf 1 gesetzt |
| trash | Nein (readonly) | **Ja** – spiegelt Zotero-Papierkorb (deleted) |

**Hinweis:** Alle Felder readonly. Nur Operation `show`, kein Edit-Formular. Trennung von published (Contao, Redaktion) und trash (Zotero, Sync). Frontend filtert: published=1 AND trash=0.

---

## tl_zotero_creator_map (Mapping Zotero-Creator → tl_member)

| Feld | Im Backend bearbeitbar? | Anpassungen beim erneuten Sync überschrieben? |
|------|-------------------------|-----------------------------------------------|
| id | Nein (automatisch) | – |
| tstamp | Nein | Ja |
| zotero_firstname | Ja | Nein – nur beim Insert vom Sync gesetzt |
| zotero_lastname | Ja | Nein – nur beim Insert vom Sync gesetzt |
| member_id | Ja (Select) | **Nein** – ausschließlich Redaktion; Sync setzt bei neuem Creator null |

**Hinweis:** Sync legt neue Creator-Einträge an (zotero_firstname, zotero_lastname, member_id=null). Backend: Nur member_id zuordnen (tl_member). notCreatable – Einträge kommen vom Sync.

---

## Zusammenfassung

- **Sync-überschriebene Felder:** Im DCA auf readonly gesetzt.
- **download_attachments:** Insert = 0; bei Update unverändert.
- **published:** Nicht mehr vom Sync überschrieben – ausschließlich Redaktion.
- **trash:** Zotero-Papierkorb (deleted); nur vom Sync gesetzt. Frontend: published=1 AND trash=0.
- **tl_zotero_creator_map:** Sync legt Einträge an (member_id=null); nur member_id von Redaktion bearbeitbar. notCreatable.
