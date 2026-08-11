=== Balíkovna for WooCommerce ===
Contributors: lukasberan
Tags: woocommerce, shipping, balikovna, ceska-posta, pickup
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
<!-- x-release-please-start-version -->
Stable tag: 1.26.7
<!-- x-release-please-end -->
License: GPLv3 or later

Integrace České pošty (Balíkovna, Balík na adresu, Balík Do ruky, Balík Na poštu) do WooCommerce: shipping metody, výběr výdejního místa přes oficiální widget, ukládání k objednávce, e-maily, admin a CSV export pro Podání Online.

== Description ==

* Shipping metody **Balíkovna**, **Balík na adresu (Balíkovna)**, **Balík Do ruky**, **Balík Na poštu** s podporou shipping zón.
* Cenotvorba: fixní cena, váhová tabulka (`max_kg|cena`), volitelný práh „zdarma od" z mezisoučtu celého košíku napříč shipping packages.
* Produkty Balíkovna se nabízejí jen pro CZ zásilky do 15 kg a 50 × 50 × 50 cm; produkty musí mít vyplněnou hmotnost a rozměry.
* Checkout pro Balíkovnu vyžaduje platný e-mail a české mobilní číslo s předvolbou země.
* Výběr výdejního místa přes oficiální widget České pošty v modálním okně (`b2c.cpost.cz/locations/`); volitelný telefon z widgetu doplní chybějící WooCommerce billing telefon.
* Serverové ověření ID a typu pobočky proti kanonickému seznamu ČP; samostatný výběr pro každý shipping package.
* Podpora **klasického shortcode checkoutu** i **Block Checkoutu** (Store API rozšíření).
* Uložení zvoleného místa k objednávce, zobrazení v adminu, v e-mailech (zákazník i admin) a na stránce Děkujeme / Můj účet.
* Sloupec **Balíkovna** v přehledu objednávek (HPOS i klasické).
* Hromadná akce **Export Balíkovna (CSV Podání Online)** v přehledu objednávek - CSV ve Windows-1250, středník jako oddělovač, per-package hmotnost a hodnota obsahu, atomická validace všech řádků.
* HPOS ready, kompatibilní s `cart_checkout_blocks`.
* **Automatické aktualizace** z GitHub Releases (Plugin Update Checker, MIT) - po první instalaci se další verze zobrazují v WP admin → Aktualizace stejně jako u pluginů z wordpress.org.
* **Diagnostický mód**: při zapnutém `WP_DEBUG` se do konzole prohlížeče logují přijaté `postMessage` zprávy z widgetu (vhodné pro vývoj a ladění integrace).
* Připraveno pro **i18n** (`languages/balikovna-wc.pot`).

== Konfigurace ==

1. WooCommerce → Nastavení → Doprava → vyberte zónu → **Přidat dopravu** → vyberte službu ČP.
2. Nastavte název, typ ceny, případně váhovou tabulku nebo práh dopravy zdarma.
3. Zákazník v checkoutu zvolí službu ČP a klikne na „Vybrat výdejní místo" (u Balíkovny / Balíku Na poštu).

== Filtry pro vývojáře ==

* `balikovna_wc_services` - úprava seznamu dostupných služeb ČP.
* `balikovna_wc_package_metrics` - úprava vypočtené hmotnosti, rozměrů a objemu shipping package.
* `balikovna_wc_widget_url` - URL widgetu výběru pobočky (`$url`, `$type`).
* `balikovna_wc_widget_phone` - `true` zapne pole pro telefon; platný výsledek doplní chybějící `billing_phone` (default `false`).
* `balikovna_wc_free_shipping_subtotal` - úprava mezisoučtu celého košíku pro práh dopravy zdarma.
* `balikovna_wc_export_headers` - hlavičky CSV exportu.
* `balikovna_wc_export_row` - úprava řádku exportu (`$row, $order, $point, $service_id`).
* `balikovna_wc_cod_methods` - seznam payment method ID považovaných za dobírku.
* `balikovna_wc_default_subject` - default Subjekt v CSV (`F` = fyzická, `P` = právnická).
* `balikovna_wc_valid_recipient_phone` - vlastní ověření normalizovaného telefonu příjemce.
* `balikovna_wc_recipient_contact_errors` - úprava výsledných chyb kontaktu pro zvolené služby.
* `balikovna_wc_debug` - vynucený diagnostický mód i bez `WP_DEBUG`.
* `balikovna_wc_point_validation_result` - vlastní validační výsledek pobočky.
* `balikovna_wc_points_directory` - vlastní kanonický seznam poboček pro daný typ.
* `balikovna_wc_points_api_url` - URL API seznamu poboček.
* `balikovna_wc_points_cache_ttl` - doba cache seznamu poboček.
* `balikovna_wc_points_max_stale_age` - maximální stáří nouzového seznamu při výpadku API (výchozí 30 dní).

== CSV pro Podání Online ==

Hlavičky a struktura odpovídají importní šabloně Podání Online (sloupce A-O) potvrzené podporou ČP. Každý shipping item tvoří samostatný řádek s vlastní hmotností a hodnotou obsahu. Pro **NB** se použije `Balíkova` a ID balíkovny, pro **NP** adresa, PSČ a město vybrané pošty. Sloupec **Služby** se naplní z nastavení konkrétní shipping metody. Export přijímá jen CZK, dobírku zaokrouhlí na celé koruny a při neúplné zásilce nevytvoří částečný soubor. Import v Podání Online musí mít odpovídající mapování A-O a smluvní kódy. Lze jej upravit filtrem `balikovna_wc_export_row`.

== Externí služby a soukromí ==

Picker otevírá iframe `https://b2c.cpost.cz/locations/`, takže Česká pošta obdrží běžná HTTP metadata návštěvy. Geolokaci předá prohlížeč widgetu jen po souhlasu zákazníka. Při zapnutém `phone=true` zadává zákazník telefon přímo ve widgetu České pošty a plugin jej může uložit jako WooCommerce `billing_phone`. Server pluginu stahuje veřejně dostupný JSON seznam poboček pro ověření výběru, bez údajů zákazníka nebo objednávky. Endpoint nemá zveřejněný verzovaný integrační kontrakt; zdroj je proto filtrovatelný a nouzová data mají maximální stáří. Zásady provozovatele: https://www.ceskaposta.cz/ochrana-osobnich-udaju

== Známá omezení ==

* Plugin aktuálně vytváří objednávku a CSV pro Podání Online; zásilku automaticky nezakládá u České pošty.
* Není implementována Balíkovna plus, B2B-ZSK nAPI, tisk štítků, tracking, storna, vratky ani podací reporty.
* Kódy produktů a doplňkových služeb musí odpovídat smlouvě a konfiguraci Podání Online.
* Automatické testy používají runtime stuby a nenahrazují browserový end-to-end test na skutečné instalaci WooCommerce.

== Roadmap ==

* Balíkovna plus.
* B2B-ZSK nAPI - vytvoření zásilek, tisk štítků, tracking, storna a reporty.
* Vratky a související B2B workflow.
* Alternativní picker až nad stabilním a dokumentovaným číselníkem výdejních míst.
* Reálné end-to-end testy Classic/Block Checkoutu, pay-for-order a HPOS.

== Changelog ==

= 1.26.6 =
* 🐛 Opravy chyb: resolve checkout and release audit findings

= 1.26.5 =
* 🛠 CI: bump actions/checkout from 6 to 7 ([#42](https://github.com/luberan/balikovna-woocommerce/issues/42))

= 1.26.4 =
* 🐛 Opravy chyb: harden CSV export and picker postMessage origin check

= 1.26.3 =
* 🛠 CI: bump googleapis/release-please-action from 4 to 5 ([#39](https://github.com/luberan/balikovna-woocommerce/issues/39))

= 1.26.2 =
* 🐛 Opravy chyb: harden checkout integration and exact shipping-method matching

= 1.26.1 =
* 📚 Dokumentace: switch release badge to badgen (shields.io token pool errors)

= 1.26.0 =
* 🚀 Nové funkce: add Balíkovna 'Balík na adresu' shipping method (NA)

= 1.25.0 =
* 🐛 Opravy chyb: **ci:** remove broken auto-merge to prevent release-please loop
* 🐛 Opravy chyb: use POST_OFFICE widget type and persist picker selection

= 1.10.0 =
* 🐛 Opravy chyb: **ci:** use 'chore:' prefix in sync commit to avoid release-please bump loop

= 1.3.6 =
* 🐛 Opravy chyb: **ci:** clone without --depth so PR branch is available for merge
* 🛠 CI: merge release PR via git+PAT instead of gh CLI to bypass anti-loop

= 1.3.5 =
* 📚 Dokumentace: sync readme.txt changelog with v1.3.4 [skip ci]
* 🛠 CI: auto-merge release-please PR via PAT for fully unattended releases

= 1.3.4 =
* 📚 Dokumentace: sync readme.txt changelog with v1.3.3 [skip ci]
* 🛠 CI: use PAT for release-please to break GITHUB_TOKEN anti-loop

= 1.3.3 =
* 🐛 Opravy chyb: do not break block checkout when picker not yet selected
* 📚 Dokumentace: sync readme.txt changelog with v1.3.2 [skip ci]
* 🛠 CI: add workflow_dispatch trigger to release-please for manual re-runs

= 1.3.2 =
* 🐛 Opravy chyb: override changelog modal with local readme.txt history
* 📚 Dokumentace: sync readme.txt changelog with v1.3.1 [skip ci]

= 1.3.1 =
* 🐛 Opravy chyb: **ci:** sync readme.txt changelog back to main so PUC sees it
* 🐛 Opravy chyb: trigger release to publish synced readme.txt changelog to main

= 1.3.0 =
* 🚀 Nové funkce: **ci:** include full changelog history in release ZIP readme.txt

= 1.2.2 =
* 🐛 Opravy chyb: **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives
* 🐛 Opravy chyb: harden Plugin Update Checker bootstrap against fatals

= 1.2.1 =
* 🐛 Opravy chyb: **ci:** build release ZIP in the same workflow as release-please

= 1.2.0 =
* 🚀 Nové funkce: GitHub-driven auto-updates via Plugin Update Checker

= 1.1.0 =
* 🚀 Nové funkce: implement official CP picker payload and Podani Online CSV format
* 🚀 Nové funkce: per-method admin setting for CP service codes (CSV column I)
* 🐛 Opravy chyb: declare shipping method instance properties (PHP 8.2+ deprecation)
* 🐛 Opravy chyb: load textdomain on init hook (WP 6.7+ requirement)
* 🔧 Kompatibilita: bump targets to WP 7.0, PHP 8.5, WC 10.8
* 🔧 Kompatibilita: declare realistic PHP minimum 7.4 (tested up to 8.5)
* 🔧 Kompatibilita: declare realistic WordPress minimum 6.0 (tested up to 7.0)
* 📚 Dokumentace: add Author URI to plugin header
* 📚 Dokumentace: align license metadata with GPL-3.0-or-later
* 📚 Dokumentace: fix plugin and POT repository URL to luberan/balikovna-woocommerce
* 📚 Dokumentace: refresh README with current feature set and CP-confirmed details
* 🛠 CI: add least-privilege permissions to workflow
* 🛠 CI: add release-please for automated changelog and version bumps
* 🛠 CI: allow phpcodesniffer-composer-installer plugin (Composer 2.2+)
* 🛠 CI: build proper plugin ZIP on release publish
* 🛠 CI: bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2))
