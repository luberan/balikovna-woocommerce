# Testování pluginu

Jednotkové testy běží bez WordPress databáze. Integrační a browserové testy používají skutečný WordPress a WooCommerce podle [versions.json](versions.json), nad samostatnou MySQL/MariaDB databází s InnoDB. Instalační skript odmítne databázi bez prefixu `balikovna_test_` a existující adresář bez testovacího markeru. Nepoužívejte produkční přístupové údaje.

## Rychlé kontroly

```sh
composer install
composer test
composer lint
composer vendor:check
npm ci --ignore-scripts
npm run test:js
```

Po úpravě translatovatelných řetězců spusťte `composer pot`. Při aktualizaci uzamčeného Parsedown spusťte `php .github/scripts/vendor-parsedown.php`; výsledný namespacovaný soubor, manifest a licence jsou součástí distribuce a kontrolují se proti Composer locku.

## Reálná Integrace

Potřebujete PHP s `mysqli`, `pdo_mysql`, `curl`, `zip`, `mbstring`, `openssl` a lokální MariaDB/MySQL server. Účet musí smět vytvořit novou testovací databázi. Chromium testy potřebují Node.js 20 nebo novější.

Příklad pro Bash:

```sh
export BALIKOVNA_TEST_SITE=/tmp/balikovna-integration
export BALIKOVNA_TEST_DB_NAME=balikovna_test_local
export BALIKOVNA_TEST_DB_HOST=127.0.0.1
export BALIKOVNA_TEST_DB_PORT=3306
export BALIKOVNA_TEST_DB_USER=root
export BALIKOVNA_TEST_STORAGE=hpos
export BALIKOVNA_TEST_BASE_URL=http://127.0.0.1:8873
composer build
php .github/scripts/setup-integration.php
composer test:integration
node node_modules/@playwright/test/cli.js install chromium
npm run test:e2e
```

Případné heslo testovací databáze nastavte lokálně přes `BALIKOVNA_TEST_DB_PASSWORD`; neukládejte je do repozitáře. Varianta `BALIKOVNA_TEST_STORAGE=cpt` ověřuje klasické objednávkové úložiště. Použijte jiný testovací adresář a název databáze pro nezávislý běh každé varianty.

Na Windows použijte ekvivalentní `$env:BALIKOVNA_TEST_*` proměnné ve stejné terminálové relaci. Je-li třeba zapnout `mysqli` pouze pro test, nastavte `BALIKOVNA_PHP_ARGS` na JSON `["-d","extension=mysqli"]`. Tato pole argumentů se předávají podprocesům PHP. `BALIKOVNA_PHP_BINARY` dovoluje určit PHP spustitelný soubor pro Playwright.

`BALIKOVNA_TEST_WP_ARCHIVE` a `BALIKOVNA_TEST_WC_ARCHIVE` dovolují použít už stažené oficiální archivy. SHA-256 WooCommerce se vždy kontroluje proti [versions.json](versions.json). Na Windows musí být v PHP nastavený aktuální důvěryhodný CA bundle pro stahování; nevypínejte ověřování TLS.

## Rozsah

- CPT i HPOS: úspěšný zápis stavu, zachování ručního storna, odmítnutí zastaralého podacího čísla, zachování cizí transakce a blokování konkurujícího databázového spojení pod InnoDB zámkem.
- Výběr objednávek: skutečné stránkování bez zahazování přetékající části dávky.
- Legacy checkout: existující jednoznačné původní místo je přijato, nové drafty a nejednoznačná přiřazení nikoli.
- Export: neúplná hmotnost a překročený limit se odmítnou; virtuální položky se do fyzické hmotnosti nezapočítávají.
- Cache: při výpadku se provede jediný dotaz během cooldownu, úspěšné obnovení uvolní zámek.
- Browser: Classic i Block Checkout pro jeden a dva balíky, desktop a mobil, serverové odmítnutí bez místa, ověření původu zprávy, samostatná místa a kontrola trvalých údajů a CSV po odeslání.
- Parser, šifrování a cleanup: injekce atributů, kolize globálního parseru, hash manifestu, rotace klíče, migrace a zachování objednávek.

Playwright spouští dočasný PHP server pouze na localhost a nepřebírá již běžící server. Jeho log je v testovacím adresáři jako `php-server.log`; chyby WordPressu jsou v `wp-content/debug.log`. Server se po sadě ukončí. Výsledky neúspěšných testů včetně screenshotů a trace jsou v ignorovaném adresáři `test-results`.

Testovací widget a referenční seznam jsou řízené fixtures; testy nevolají nAPI, neposílají e-maily ani nevytvářejí skutečné zásilky. Reálné CIS/ZSK přihlášení a import CSV do Podání Online vyžadují zvláštní akceptační ověření se smluvním testovacím účtem dopravce. Interpretace HMAC klíče se bez oficiálního referenčního vektoru nemění.

Veřejný seznam poboček lze zkontrolovat bez přihlašovacích údajů příkazem `php .github/scripts/check-points-api.php`. Denní CI workflow sleduje oba typy míst, limit 8 MiB a parsovatelnost aktuálním produkčním parserem.