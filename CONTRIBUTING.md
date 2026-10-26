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
3. PHP kód cílí na **PHP 7.4+** (testováno proti aktuální stabilní větvi 8.5), WordPress 6.9+, WooCommerce 10.8+.
4. Žádné nové runtime závislosti bez diskuse v issue.
5. Texty viditelné uživateli vždy přes `__()` / `_e()` s textdomain `balikovna-wc` a po přidání spusťte regeneraci POT:
   ```
   composer pot
   ```
6. Commit message v angličtině, imperativ, krátký první řádek (max 72 znaků), volitelně rozšířený popis.
7. PR popíše motivaci, dopad, kompatibilitu (klasický vs. Block Checkout, HPOS) a screen/GIF u UI změn.

## Spuštění lokálně

Runtime plugin nemá kompilaci assetů – stačí jej naklonovat do `wp-content/plugins/`. Release staging se skládá příkazem `composer build`.

Doporučená lokální stack (libovolná z nich):
- [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (`npx wp-env start`)
- [LocalWP](https://localwp.com/)
- DDEV / Lando

Pro otestování diagnostického módu zapněte v `wp-config.php`:
```php
define( 'WP_DEBUG', true );
```

## Testy a lint

Nejprve nainstalujte přesně uzamčené vývojové závislosti:

```
composer install
```

PHP syntax check (běží i v CI):
```
find . -name "*.php" -not -path "./vendor/*" -not -path "./includes/lib/*" -print0 | xargs -0 -n1 php -l
```

Regresní testy a blokující WordPress/PHPCompatibility kontrola:
```
composer test
composer lint
```

## Automatizace údržby a vydávání

- Dependabot kontroluje GitHub Actions, Composer a npm v režimu `daily`, tedy každý pracovní den v 05:17 v časové zóně `Europe/Prague`. Bezpečnostní aktualizace reagují na nalezené zranitelnosti nezávisle na tomto rozvrhu. Minor/patch aktualizace se seskupují po ekosystému; major aktualizace zůstávají oddělené.
- Automatické squash sloučení je omezené na minor/patch aktualizace explicitně povolených Actions a vývojových Playwright závislostí v [pravidlech auto-merge](.github/scripts/dependabot-automerge.cjs). Composer, major aktualizace, neznámé závislosti a změny upravené maintainerem vyžadují ruční posouzení. Bot neobchází ochranu větve ani neschvaluje vlastní PR.
- Hlavní větev vyžaduje PR a úspěšný check `CI result` z GitHub Actions nad aktuální větví. Tento souhrnný check selže také při přeskočení nebo zrušení některé povinné sady testů. Pravidla se nastavují v GitHub repository rulesetu; samotná změna YAML ochranu větve nezapíná.
- Release Please připraví verze, normalizovaný changelog i jeho převod do [readme.txt](readme.txt) přímo v release PR a explicitně spustí CI na jeho konečném commitu. **Release PR se slučuje ručně**, až po úspěšných kontrolách. Následně workflow vytvoří tag a GitHub Release a přiloží distribuční ZIP. Z vydávání se již nezapisuje přímo do hlavní větve a není potřeba PAT ani výjimka z jejích ochran.
- Sloučení provedené přes `GITHUB_TOKEN` samo nespouští další workflow. Denní běh Release Please v 08:41 UTC proto doplní release PR i po automaticky sloučených aktualizacích. Pro okamžitou kontrolu lze Release Please spustit ručně; vstup `release_tag` zůstává určený k doplnění chybějícího ZIPu existujícího vydání.
- Denní kontrola adresáře poboček při selhání založí nebo aktualizuje jediné incident issue s odkazem na běh. Po zotavení je uzavře a při opakování znovu otevře. Souběžné kontroly se řadí do fronty, aby nevznikala duplicitní hlášení; podrobnosti zůstávají v logu Actions.

## Licence

Příspěvkem souhlasíte, že váš kód bude šířen pod stejnou licencí jako celý projekt – **GPL-3.0-or-later**.
