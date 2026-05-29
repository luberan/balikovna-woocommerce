=== Balíkovna for WooCommerce ===
Contributors: lukasberan
Tags: woocommerce, shipping, balikovna, ceska-posta, pickup
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.0 <!-- x-release-please-version -->
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

= 1.0.0 =
* První vydání. Balíkovna, Balík Do ruky, Balík Na poštu.
