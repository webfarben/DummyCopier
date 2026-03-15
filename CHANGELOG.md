# Changelog

Alle nennenswerten Aenderungen an `webfarben/contao-dummy-copier` werden in dieser Datei dokumentiert.

## [1.1.5] - 2026-03-15

### Fixed
- Kompatibilitaet fuer Contao 4.13 und 5.x verbessert: Array-Serialisierung nutzt jetzt natives PHP `serialize()` statt `StringUtil::serialize()`.

## [1.1.4] - 2026-03-15

### Fixed
- SQL-Fehler in Umgebungen ohne `sorting`-Spalte in `tl_news` bzw. `tl_calendar_events` behoben.
- Sortierung fuer News/Events auf robuste ORDER-BY-Klauseln ohne `sorting` angepasst.

## [1.1.3] - 2026-03-15

### Changed
- Backend-Modul-Icon (`public/icon.svg`) hinzugefuegt/aktualisiert.
- README auf aktuellen Funktionsumfang und Installationsweg ueber Packagist gebracht.

## [1.1.2] - 2026-03-12

### Changed
- Paket-Metadaten (`homepage`, `support.source`, `support.issues`) auf GitHub als kanonische Quelle umgestellt.

## [1.1.1] - 2026-03-12

### Fixed
- Rewiring fuer interne News-Referenzen (`related`) in kopierten News verbessert.
- Reader-Modul-Referenzen in kopierten Modulen korrigiert (`news_readerModule`, `cal_readerModule`).

## [1.1.0] - 2026-03-12

### Added
- Kopieren von Newsarchiven und Newsbeitraegen (`tl_news_archive`, `tl_news`).
- Kopieren von Kalendern und Events (`tl_calendar`, `tl_calendar_events`).
- Auswahlfelder fuer Newsarchive/Kalender im Backend-Modul.
- Ergebnisdaten um Zaehler und Mapping fuer News/Kalender erweitert.

### Changed
- Referenz-Umschreibung fuer kopierte Module um Archivzuordnungen erweitert (`news_archives`, `cal_calendar`).
