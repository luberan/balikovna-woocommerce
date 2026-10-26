# Testování pluginu

Jednotkové testy běží bez WordPress databáze; testy balíčku potřebují PHP rozšíření `zip`. Integrační a browserové testy instalují skutečný distribuční ZIP do WordPressu/WooCommerce podle profilů v [versions.json](versions.json), nad samostatnou MySQL/MariaDB databází s InnoDB. Instalační skript odmítne databázi bez prefixu `balikovna_test_` a existující adresář bez testovacího markeru. Nepoužívejte produkční přístupové údaje.

## Rychlé kontroly

```sh
composer install
composer test
composer lint
composer analyse
composer vendor:check
composer build
npm ci --ignore-scripts
npm run test:js
```

Po úpravě translatovatelných řetězců spusťte `composer pot`. Při aktualizaci uzamčeného Parsedown spusťte `php .github/scripts/vendor-parsedown.php`; výsledný namespacovaný soubor, manifest a licence jsou součástí distribuce a kontrolují se proti Composer locku. Při aktualizaci Plugin Update Checkeru zachovejte lokální úpravy označené `Local patch`; `composer lint` kontroluje kompatibilitu přibalených knihoven s PHP 7.4+.

`composer build` vytvoří `build/balikovna-woocommerce/` i `build/balikovna-woocommerce.zip` stejným postupem jako release workflow. Normalizace changelogu mění jen balíček, nikoli zdrojové soubory. Regresní testy kontrolují obsah a kořen ZIPu, licence, vyloučení vývojových souborů, přestavění bez pozůstatků a odmítnutí nesouhlasících verzí.

## Statická analýza

`composer analyse` spouští PHPStan na úrovni 5 s cílovým PHP 7.4.33 nad vstupním souborem pluginu, odinstalací a vlastními třídami. Přibalené knihovny slouží pro objevování symbolů, ale jejich implementace se tímto nástrojem neaudituje. WordPress extension a WooCommerce stuby poskytují frameworkové typy.

Obranné kontroly zůstávají platné i pro neplatné vstupy hooků a měnící se databázový stav; PHPDoc ani opakované stavové volání se nepovažují za neměnné runtime záruky. Úzké opravy skutečných WooCommerce kontraktů v [phpstan/woocommerce.stub](phpstan/woocommerce.stub) zachovávají smíšené hodnoty metadat a nullable košík. Konfigurace nepoužívá baseline ani `ignoreErrors`. Negativní regresní test ověřuje, že skutečná chyba typu argumentu analýzou neprojde.

## Reálná Integrace

Potřebujete PHP s `mysqli`, `pdo_mysql`, `curl`, `zip`, `mbstring`, `openssl` a lokální MariaDB/MySQL server. Účet musí smět vytvořit novou testovací databázi. Chromium testy potřebují Node.js 20 nebo novější.

| Profil | WordPress | WooCommerce | PHP v CI | Úložiště |
|---|---|---|---|---|
| `current` (výchozí) | 7.1.2 | 11.2.0 | 7.4, 8.5 | CPT, HPOS |
| `minimum` | 6.9 | 10.8.0 | 7.4 | CPT, HPOS |

Profil se vybírá přes `BALIKOVNA_TEST_VERSION_PROFILE`. Integrační test porovnává skutečně načtené verze a režim úložiště s vybraným profilem, nestačí tedy pouze označení jobu. Pro jiný profil nebo režim úložiště použijte nový testovací adresář a databázi.

Příklad pro Bash:

```sh
export BALIKOVNA_TEST_SITE=/tmp/balikovna-integration
export BALIKOVNA_TEST_DB_NAME=balikovna_test_local
export BALIKOVNA_TEST_DB_HOST=127.0.0.1
export BALIKOVNA_TEST_DB_PORT=3306
export BALIKOVNA_TEST_DB_USER=root
export BALIKOVNA_TEST_STORAGE=hpos
export BALIKOVNA_TEST_VERSION_PROFILE=current
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

## Upgrade distribučního ZIPu

CI pro `current` / PHP 8.5 ověřuje upgrade v CPT i HPOS, vždy v další samostatné instalaci a databázi. Příklad pro Bash:

```sh
export BALIKOVNA_TEST_SITE=/tmp/balikovna-upgrade-local
export BALIKOVNA_TEST_DB_NAME=balikovna_test_upgrade_local
export BALIKOVNA_TEST_STORAGE=hpos
export BALIKOVNA_TEST_VERSION_PROFILE=current
composer build
php .github/scripts/setup-integration.php --baseline
php tests/integration/upgrade.php
```

Použijte stejné lokální databázové připojení jako u běžné integrace. Baseline je vydaný instalační ZIP připnutý verzí a SHA-256 v sekci `upgrade` souboru [versions.json](versions.json), aktuálně 1.29.2. Při změně baseline ověřte digest oficiálního release assetu; zdrojový archiv GitHub tagu není instalační balíček.

Test nejprve ověří přesný obsah původního vydání, připraví syntetické nastavení a objednávku a použije skutečný `Plugin_Upgrader::bulk_upgrade` s lokální aktualizační odpovědí, stejně jako AJAX aktualizace v administraci WordPressu. Nový PHP proces následně porovná všechny instalované soubory s cílovým ZIPem, aktivaci, CPT/HPOS, nastavení včetně dešifrovaných testovacích přihlašovacích údajů, objednávku i metadata výdejního místa. Kontroluje se také odstranění zastaralého souboru; test plugin ručně znovu neaktivuje.

PR build může mít před release bumpem stejné číslo jako vydání; verze se kvůli testu nevymýšlí ani nemění, rozhodující je přesná kontrola obsahu. Downgrade je odmítnut. Test odmítá externí přihlašovací údaje dopravce, neposílá zásilky ani e-maily a nemá povolené externí WordPress HTTP požadavky.

## CI diagnostika a cache

CI ruší zastaralé běhy stejného PR nebo větve a omezuje délku jobů; release publikování se neruší. Cache obsahuje Composer download archives a npm cache podle lock souborů, ne hotové `vendor` nebo `node_modules`. Každý běh proto stále provádí uzamčenou instalaci a audity.

Při selhání integrace/E2E se v detailu běhu uloží artefakt označený profilem, PHP, CPT/HPOS a číslem pokusu. Obsahuje dostupné screenshoty, Playwright traces, PHP logy a konzolový výstup integračního/upgrade testu a uchovává se sedm dní. Záznam přes `tee` používá `pipefail`, aby nezakryl chybu testu. Trace lze otevřít lokálním Playwright trace viewerem; neposílejte diagnostiku s reálnými údaji externím službám.

## Rozsah

- CPT i HPOS: úspěšný zápis stavu, zachování ručního storna, odmítnutí zastaralého podacího čísla, zachování cizí transakce a blokování konkurujícího databázového spojení pod InnoDB zámkem. Hooky změny stavu a e-maily běží až po potvrzení transakce a chyba pozdějšího hooku stav nevrátí ani nezopakuje e-mail.
- Zámky: souběžně vložený zámek jiného procesu se nepřepíše ani při zastaralé cache WordPressu.
- Výběr objednávek: skutečné stránkování bez zahazování přetékající části dávky; objednávka, kterou WooCommerce nedokáže načíst, se nahlásí samostatně a ostatní objednávky ze stejné stránky se zpracují.
- Legacy checkout: existující jednoznačné původní místo je přijato, nové drafty a nejednoznačná přiřazení nikoli.
- Export: neúplná hmotnost a překročený limit se odmítnou; virtuální položky se do fyzické hmotnosti nezapočítávají.
- Cache poboček: při výpadku se provede jediný dotaz během cooldownu, úspěšné obnovení uloží malé části seznamu a uvolní zámek, zastaralý seznam se obslouží bez stahování a obnoví na pozadí.
- Nastavení dopravy: neplatná váhová tabulka vrátí chybu do okna metody v zóně.
- Aktualizace: první požadavek po úklidu znovu naplánuje kontrolu aktualizací bez předčasného načtení překladů WooCommerce.
- Bloky: skript pickeru je závislostí frontend skriptu bloku Pokladna i při vykreslení mimo obsah stránky.
- Browser: Classic i Block Checkout pro jeden a dva balíky, desktop a mobil, serverové odmítnutí bez místa, ověření původu zprávy, samostatná místa a kontrola trvalých údajů a CSV po odeslání.
- Parser, šifrování a cleanup: injekce atributů, kolize globálního parseru, hash manifestu, rotace klíče, migrace a zachování objednávek.

Playwright spouští dočasný PHP server pouze na localhost a nepřebírá již běžící server. Jeho log je v testovacím adresáři jako `php-server.log`; chyby WordPressu jsou v `wp-content/debug.log`. Server se po sadě ukončí. Výsledky neúspěšných testů včetně screenshotů a trace jsou v ignorovaném adresáři `test-results`.

Testovací widget a referenční seznam jsou řízené fixtures; testy nevolají nAPI, neposílají e-maily ani nevytvářejí skutečné zásilky. Reálné CIS/ZSK přihlášení a import CSV do Podání Online vyžadují zvláštní akceptační ověření se smluvním testovacím účtem dopravce. Interpretace HMAC klíče se bez oficiálního referenčního vektoru nemění.

Veřejný seznam poboček lze zkontrolovat bez přihlašovacích údajů příkazem `php .github/scripts/check-points-api.php`. Denní CI workflow sleduje oba typy míst, limit 8 MiB a parsovatelnost aktuálním produkčním parserem.

`npm run test:js` ověřuje také pravidla Dependabot auto-merge a životní cyklus incident issue (první i opakované selhání, stránkování, opětovné otevření, zotavení a chyby GitHub API). Tyto testy používají mock API a nevytvářejí skutečná issues. `composer test` kontroluje konfiguraci denních aktualizací, úplnost povinného CI checku, důvěryhodný checkout v privilegovaném workflow a přípravu release PR bez přímého zápisu do hlavní větve. Release CI používá skutečný PR workflow po ručním schválení, nikoli náhradní dispatch ani uměle zapisované statusy.