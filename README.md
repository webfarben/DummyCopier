# Contao Dummy Copier

Dieses Bundle stellt ein Backend-Modul `Dummy Copier` bereit, um bestehende Dummydaten in Contao kontrolliert zu vervielfaeltigen und interne Referenzen auf die neuen Zielobjekte umzubiegen.

## Funktionsumfang

- rekursives Kopieren von Seitenbaeumen aus `tl_page`
- optionales Kopieren von Artikeln und verschachtelten Inhaltselementen aus `tl_article` und `tl_content`
- optionales Kopieren von Modulen aus `tl_module`
- optionales Kopieren von Newsarchiven samt Newsbeitraegen aus `tl_news_archive` und `tl_news`
- optionales Kopieren von Kalendern samt Events aus `tl_calendar` und `tl_calendar_events`
- optionales Spiegeln von Verzeichnissen im Dateisystem
- Dry-Run zur Vorschau ohne Schreibzugriffe

## Automatische Referenzanpassungen

- `jumpTo` in kopierten Seiten, Modulen, Content-Elementen, Newsarchiven, News, Kalendern und Events
- Modulreferenzen in Content-Elementen vom Typ `module`
- Alias-Referenzen in verschachtelten Content-Elementen (`cteAlias`)
- Archiv-Zuordnungen in kopierten Modulen (`news_archives`, `cal_calendar`)
- Reader-Module in kopierten Modulen (`news_readerModule`, `cal_readerModule`)
- verwandte News (`related`), sofern die referenzierten News ebenfalls mitkopiert wurden

## Installation

Installation ueber Packagist:

```bash
composer require webfarben/contao-dummy-copier
```

Danach wie ueblich:

```bash
php vendor/bin/contao-setup
php vendor/bin/console contao:migrate
```

Das Backend-Modul `Dummy Copier` erscheint anschliessend unter `System`.

## Bedienung

- Quellobjekte werden ueber Mehrfachauswahlfelder ausgewaehlt.
- Seiten, Module, Newsarchive, Kalender und Verzeichnisse koennen separat kombiniert werden.
- Alle Mehrfachauswahlfelder besitzen Live-Filter sowie `Alle`/`Keine` Buttons.
- Inhaltselemente von Seiten werden bei aktiver Option automatisch mitkopiert.
- Ueber ein Praefix lassen sich Titel, Namen und Aliase der Kopien kenntlich machen.

## Hinweise

- Nach Dateikopien ggf. `php vendor/bin/console contao:filesync` ausfuehren, damit die DBAFS-Daten synchronisiert werden.
- Das Bundle ist fuer pragmatische Redaktions- und Setup-Workflows gedacht. Projektspezifische Sonderfelder oder Referenzen koennen bei Bedarf erweitert werden.

## Changelog

- Siehe `CHANGELOG.md` fuer die dokumentierten Aenderungen ab `1.1.0`.
