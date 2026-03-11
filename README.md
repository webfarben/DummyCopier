# Contao Dummy Copier (Scaffold)

Dieses Bundle stellt ein Backend-Modul `Dummy Copier` bereit, um bestehende Dummyseiten, Inhalte, Module und Verzeichnisse zu kopieren und Referenzen automatisiert umzubiegen.

## Enthaltene Funktionen

- Rekursives Kopieren von Seitenbaeumen (`tl_page`)
- Optionales Kopieren von Artikeln und Content (`tl_article`, `tl_content`)
- Optionales Kopieren von Modulen (`tl_module`)
- Automatisches Umstellen von:
  - Content-Elementen vom Typ `module` auf kopierte Modul-IDs
  - `jumpTo` in kopierten Seiten/Modulen/Content auf kopierte Seiten, falls vorhanden
- Optionales Kopieren von Verzeichnissen (Dateisystem-Mirror)
- Dry-Run Modus ohne Schreibzugriff

## Installation

1. Bundle in dein Contao-Projekt legen (oder als VCS-Paket einbinden).
2. `composer install` oder `composer update acme/contao-dummy-copier`
3. Cache leeren.
4. Backend-Modul `Dummy Copier` unter `System` oeffnen.

## Bedienung (aktueller Stand)

- Quellobjekte werden ueber Mehrfachauswahlfelder ausgewaehlt (Seiten, Module, Content, Verzeichnisse).
- Seiten und Verzeichnisse werden in Baumdarstellung (Einrueckung nach Hierarchie) angezeigt.
- Alle Mehrfachauswahlfelder haben Live-Filter sowie `Alle`/`Keine` Buttons.
- Ziel-Elternseite wird per Auswahlfeld gesetzt.

Bei kompatibler Contao-Umgebung nutzt das Modul native `pageTree`/`fileTree` Widgets fuer Seiten und Verzeichnisse.
Falls die Widget-Initialisierung versionsbedingt fehlschlaegt, wird automatisch auf die Select-Fallbacks gewechselt.
- Setze optional Zielverzeichnis, Zielartikel-ID und Praefix.
- Aktiviere Optionen nach Bedarf (`inkl. Content`, `Module kopieren`, `Verzeichnisse kopieren`, `Dry-Run`).

Hinweis: Das Modul akzeptiert weiterhin CSV-Werte als Fallback, falls du Felder per POST automatisiert befuellst.

## Wichtige Hinweise

- Nach Verzeichnis-Kopien ggf. `contao:filesync` ausfuehren, damit DBAFS konsistent ist.
- Dieses Grundgeruest ist bewusst pragmatisch und kann erweitert werden um:
  - PageTree/FileTree Picker statt CSV
  - Feldspezifisches Mapping fuer News/Event/Archive-Felder in `tl_module`
  - Job-Queue via Messenger bei sehr grossen Kopierlaeufen
