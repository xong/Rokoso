# Coop – Kollaborationssoftware für Stadtelternvertretungen

Gemeinsames Postfach, Projekte, Kalender und Kontakte für ehrenamtliche Gremien.
Open Source unter [MIT-Lizenz](LICENSE).

> Stand und Fahrplan: [`docs/PLAN.md`](docs/PLAN.md)

## Voraussetzungen

- PHP ≥ 8.4 mit den Erweiterungen `curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, zip`
- Composer
- Docker (für MariaDB und Mailpit in der Entwicklung)

Node wird **nicht** benötigt: Tailwind läuft als Standalone-Binary, JavaScript über AssetMapper/Importmap.

## Lokale Entwicklung

```bash
composer install
docker compose up -d                 # MariaDB (Port 3307) + Mailpit (http://localhost:8025)
php bin/console tailwind:build --watch   # in eigenem Terminal
php -S 127.0.0.1:8000 -t public public/index.php   # oder: symfony serve
```

Eigene Einstellungen (z. B. `APP_SECRET`) gehören in `.env.local`.

## Qualität

```bash
composer check   # Codestil, PHPStan, PHPUnit
composer fix     # Codestil automatisch korrigieren
```

## Dokumentation

- [`App.md`](App.md) – Ideensammlung / Anforderungen
- [`docs/PLAN.md`](docs/PLAN.md) – Phasen und Fortschritt
- [`docs/ENTSCHEIDUNGEN.md`](docs/ENTSCHEIDUNGEN.md) – Architekturentscheidungen
- [`docs/OFFENE-FRAGEN.md`](docs/OFFENE-FRAGEN.md) – noch zu klären
