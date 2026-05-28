# Přispívání do Balíkovna for WooCommerce

Díky za zájem přispět! Tento dokument shrnuje, jak nahlásit chybu, navrhnout vylepšení a poslat pull request.

## Nahlášení chyby

1. Zkontrolujte [existující issues](https://github.com/luberan/balikovna-woocommerce/issues), zda už není problém nahlášený.
2. Otevřete nový bug report přes šablonu a uveďte:
   - verzi WordPressu, WooCommerce a PHP,
   - aktivní téma a další shipping/checkout pluginy,
   - kroky k reprodukci,
   - co jste očekávali a co se reálně stalo,
   - relevantní výpisy z konzole prohlížeče (zejména při zapnutém `WP_DEBUG` – plugin loguje `postMessage` z widgetu).

**Bezpečnostní zranitelnosti** prosím nehlaste ve veřejných issues – řiďte se [SECURITY.md](SECURITY.md).

## Návrh funkce

Otevřete issue typu „Feature request" s popisem use-case a alternativ, které jste zvažovali.

## Pull requesty

1. Forkněte repozitář a vytvořte větev z `main` (`git checkout -b feat/short-name`).
2. Držte se WordPress coding standards (PSR-12 pro nové soubory je také OK, ale dodržujte styl okolního kódu).
3. PHP kód cílí na **PHP 7.4+** (testováno proti aktuální stabilní větvi 8.5), WordPress 6.0+, WooCommerce 10.8+.
4. Žádné nové runtime závislosti bez diskuse v issue.
5. Texty viditelné uživateli vždy přes `__()` / `_e()` s textdomain `balikovna-wc` a po přidání spusťte regeneraci POT:
   ```
   wp i18n make-pot . languages/balikovna-wc.pot --domain=balikovna-wc
   ```
6. Commit message v angličtině, imperativ, krátký první řádek (max 72 znaků), volitelně rozšířený popis.
7. PR popíše motivaci, dopad, kompatibilitu (klasický vs. Block Checkout, HPOS) a screen/GIF u UI změn.

## Spuštění lokálně

Plugin nemá build krok – stačí naklonovat do `wp-content/plugins/` libovolné lokální WordPress instalace.

Doporučená lokální stack (libovolná z nich):
- [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (`npx wp-env start`)
- [LocalWP](https://localwp.com/)
- DDEV / Lando

Pro otestování diagnostického módu zapněte v `wp-config.php`:
```php
define( 'WP_DEBUG', true );
```

## Lint

PHP syntax check (běží i v CI):
```
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
```

Volitelně PHPCS s WordPress Coding Standards:
```
composer global require wp-coding-standards/wpcs --dev
phpcs --standard=WordPress includes/ balikovna-woocommerce.php
```

## Licence

Příspěvkem souhlasíte, že váš kód bude šířen pod stejnou licencí jako celý projekt – **GPL-3.0-or-later**.
