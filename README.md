# Filmabend – Bewertungstool

Winziges PHP-Tool: Admin legt einen Film an, auf der Übersicht erscheint ein QR-Code,
Gäste scannen ihn, geben die PIN ein und bewerten von 1–10 (Name optional). Danach
wandert der Film ins Archiv mit Durchschnitt und Einzelbewertungen. Alte Filme lassen
sich mit reinem Durchschnitt nachtragen.

## Setup

1. `db/schema.sql` in die Datenbank importieren.
2. `config.sample.php` kopieren nach `../private/config.php` (**ausserhalb** des Webroots)
   und ausfüllen: DB-Zugang, `$adminPasswordHash`, `$ratingPin`, `$baseUrl`.
   Hash erzeugen: `php -r "echo password_hash('geheim', PASSWORD_DEFAULT);"`.
   Liegt die Config woanders: `SetEnv PFBT_CONFIG /pfad/zu/config.php` (sonst wird
   `<repo>/../private/config.php` erwartet). Nur noch dieser eine Pfad ist relevant.
   Landet die Config im Webroot, bricht die App mit HTTP 500 ab, statt die
   Zugangsdaten auszuliefern.
3. Repo hochladen und den **Webroot auf `public/` zeigen lassen**. Geht das beim Hoster
   nicht, erledigt das mitgelieferte `.htaccess` die Umleitung.
4. `php tests/test_rating.php` → gibt `ok` aus.

Braucht PHP 7.4+ mit PDO/MySQL und GD (für die QR-PNGs).

### Installation als Unterordner (z.B. `xyz.de/pftb`)

Der Webroot lässt sich dann nicht auf `public/` legen, also trägt `.htaccess` die Arbeit:

1. Im Root-`.htaccess` die Zeile `RewriteBase /pftb` einkommentieren und anpassen.
2. Die Config **ausserhalb** des Docroots ablegen und `PFBT_CONFIG` setzen — der
   Default-Pfad läge sonst unter `httpdocs/private/` und wäre per Browser abrufbar.
3. `$baseUrl` inklusive Unterordner eintragen (`https://xyz.de/pftb`).

`src/`, `db/` und `tests/` haben zusätzlich ein eigenes `.htaccess` mit `Require all
denied`, damit sie auch ohne mod_rewrite nicht ausgeliefert werden.

### Update einer bestehenden Installation

Nur den `rate_limits`-Block aus `db/schema.sql` nachziehen und `$rateLimits` /
`$trustedProxies` aus `config.sample.php` in die eigene Config übernehmen (fehlen sie,
greifen die Defaults). `$adminPassword` heisst jetzt `$adminPasswordHash` und erwartet
einen `password_hash`-Wert.

## Struktur

| Pfad | Zweck |
|---|---|
| `public/index.php` | Übersicht: laufender Film mit QR-Code + Archiv |
| `public/rate.php?m=…` | PIN-Abfrage und Bewertungsformular |
| `public/qr.php?m=…` | QR-PNG für die Bewertungs-URL |
| `public/admin.php` | Login, Film anlegen/nachtragen, schliessen/öffnen, löschen |
| `public/lib/phpqrcode.php` | QR-Bibliothek (unverändert, LGPL, siehe `phpqrcode-LICENSE.txt`) |
| `src/bootstrap.php` | Config laden, Session-Cookie-Flags, Security-Header, Client-IP |
| `src/helpers.php` | Escaping, Score-/URL-Validierung, Durchschnitt, Rate Limiting |
| `db/schema.sql` | Tabellen |
| `tests/test_rating.php` | Selbsttest der Hilfsfunktionen |

## Sicherheit

Gesetzt: CSP ohne `unsafe-inline`, `nosniff`, `X-Frame-Options`, `Referrer-Policy`,
HttpOnly/SameSite/Secure-Session-Cookies (auf das App-Verzeichnis begrenzt),
`session_regenerate_id` nach Login und PIN, CSRF-Token auf allen Admin-Aktionen,
Admin-Passwort als bcrypt-Hash, Poster-URLs auf http/https begrenzt, `display_errors` aus.

### Rate Limiting

Pro IP, Werte in der Config unter `$rateLimits` (`[Versuche, Fenster in Sekunden]`):

| Bereich | Default | Zählt |
|---|---|---|
| `admin` | 5 / 15 Min | nur Fehlversuche |
| `pin` | 10 / 15 Min | nur Fehlversuche |
| `rating` | 5 / 60 Min | abgegebene Bewertungen |

Bei Erreichen: HTTP 429 mit Countdown, Formular ausgeblendet. Die Sperre gilt nur für die
auslösende IP – niemand kann andere Gäste aussperren. Fehlversuche zählen getrennt pro
Bereich, ein gesperrter Admin-Login blockiert also nicht die Bewertung.

Zwei Dinge zum Justieren:

- **Die PIN sollte 6-stellig sein.** Das Limit bremst, aber 4 Stellen sind nur 10.000
  Möglichkeiten – Länge ist der eigentliche Schutz.
- **`rating` trifft geteilte IPs.** Gäste im selben WLAN oder Mobilfunknetz teilen sich ein
  Kontingent. Bei grösseren Runden hochsetzen.

`X-Forwarded-For` wird nur von IPs aus `$trustedProxies` akzeptiert (bei netcup leer
lassen), sonst könnte jeder das Limit per gefälschtem Header umgehen. In der DB landet nur
ein SHA-256 der IP, keine Klartext-Adresse.

Mehrfachbewertung wird zusätzlich per Cookie verhindert – umgehbar, aber für einen
Filmabend reicht das; zusätzlich kann der Admin die Bewertung schliessen.
