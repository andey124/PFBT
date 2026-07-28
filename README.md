# Filmabend – Bewertungstool

Winziges PHP-Tool: Admin legt einen Film an, auf der Übersicht erscheint ein QR-Code,
Gäste scannen ihn, geben die PIN ein und bewerten von 1–10 (Name optional). Danach
wandert der Film ins Archiv mit Durchschnitt und Einzelbewertungen. Alte Filme lassen
sich mit reinem Durchschnitt nachtragen.

## Setup

1. `schema.sql` in die Datenbank importieren.
2. `config.sample.php` kopieren nach `../private/config.php` (**ausserhalb** des Webroots)
   und ausfüllen: DB-Zugang, `$adminPassword`, `$ratingPin`, `$baseUrl`.
   Liegt der Ordner woanders, den `require`-Pfad oben in `index.php`, `rate.php`,
   `qr.php` und `admin.php` anpassen.
3. Alle übrigen Dateien in den Webroot hochladen (inkl. `lib/`).
4. `php test_rating.php` → gibt `ok` aus.

Braucht PHP 7.4+ mit PDO/MySQL und GD (für die QR-PNGs).

## Dateien

| Datei | Zweck |
|---|---|
| `index.php` | Übersicht: laufender Film mit QR-Code + Archiv |
| `rate.php?m=…` | PIN-Abfrage und Bewertungsformular |
| `qr.php?m=…` | QR-PNG für die Bewertungs-URL |
| `admin.php` | Login, Film anlegen/nachtragen, schliessen/öffnen, löschen |
| `helpers.php` | Escaping, Score-Validierung, Durchschnittsberechnung |
| `lib/phpqrcode.php` | QR-Bibliothek (unverändert, LGPL, siehe `lib/phpqrcode-LICENSE.txt`) |

Mehrfachbewertung wird per Cookie verhindert – umgehbar, aber für einen Filmabend
reicht das; zusätzlich kann der Admin die Bewertung schliessen.
