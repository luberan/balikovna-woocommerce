=== Balíkovna for WooCommerce ===
Contributors: lukasberan
Tags: woocommerce, shipping, balikovna, ceska-posta, pickup
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.5 <!-- x-release-please-version -->
License: GPLv3 or later

Integrace České pošty (Balíkovna, Balík Do ruky, Balík Na poštu) do WooCommerce: shipping metody, výběr výdejního místa přes oficiální widget, ukládání k objednávce, e-maily, admin a CSV export pro Podání Online.

== Description ==

* Shipping metody **Balíkovna**, **Balík Do ruky**, **Balík Na poštu** s podporou shipping zón.
* Cenotvorba: fixní cena, váhová tabulka (`max_kg|cena`), volitelný práh „zdarma od".
* Výběr výdejního místa přes oficiální widget České pošty v modálním okně (`b2c.cpost.cz/locations/`).
* Plná kompatibilita s **klasickým shortcode checkoutem** i **Block Checkoutem** (Store API rozšíření).
* Uložení zvoleného místa k objednávce, zobrazení v adminu, v e-mailech (zákazník i admin) a na stránce Děkujeme / Můj účet.
* Sloupec **Balíkovna** v přehledu objednávek (HPOS i klasické).
* Hromadná akce **Export Balíkovna (CSV Podání Online)** v přehledu objednávek - CSV ve Windows-1250, středník jako oddělovač, sloupec s kódem služby.
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
* `balikovna_wc_widget_url` - URL widgetu výběru pobočky (`$url`, `$type`).
* `balikovna_wc_widget_phone` - `true` zapne pole pro telefon ve widgetu (default `false`).
* `balikovna_wc_export_headers` - hlavičky CSV exportu.
* `balikovna_wc_export_row` - úprava řádku exportu (`$row, $order, $point, $service_id`).
* `balikovna_wc_cod_methods` - seznam payment method ID považovaných za dobírku.
* `balikovna_wc_default_subject` - default Subjekt v CSV (`F` = fyzická, `P` = právnická).
* `balikovna_wc_debug` - vynucený diagnostický mód i bez `WP_DEBUG`.

== CSV pro Podání Online ==

Hlavičky a struktura odpovídají importní šabloně Podání Online (sloupce A-O) potvrzené podporou ČP. Pro zásilky typu **NB (Balíkovna)** se ve sloupci Ulice vyplní literál `Balíkova` a do PSČ unikátní ID balíkovny - dle pokynů ČP. Sloupec **Služby** (např. `7+45+S+41`) se naplní z nastavení konkrétní shipping metody (WooCommerce → Nastavení → Doprava → zóna → editace metody → pole „Kódy služeb ČP"). Lze také přepsat přes filtr `balikovna_wc_export_row`.

== Roadmap ==

* Vlastní Leaflet mapa nad veřejným ČP REST API (fallback k iframu pro CSP/blokátory).
* Plné B2B API České pošty - automatické vytváření zásilek, tisk PDF štítků, tracking.
* Notifikace o změně stavu zásilky.

== Changelog ==

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
