# Balíkovna for WooCommerce

Open-source WooCommerce plugin pro integraci služeb **České pošty** – Balíkovna, Balík Do ruky, Balík Na poštu.

## Funkce

- Shipping metody:
  - **Balíkovna** – doručení na výdejní místo (přes 3 500 poboček + BOXy)
  - **Balík Do ruky** – doručení na adresu
  - **Balík Na poštu** – doručení na konkrétní pobočku ČP
- **Výběr výdejního místa** přes oficiální widget České pošty (`b2c.cpost.cz/locations`)
- Plná kompatibilita s **klasickým checkoutem i Block Checkoutem** (Store API extension)
- **Cenotvorba**: fixní cena / podle hmotnosti (tabulka) / volitelný práh „zdarma od"
- Uložení místa k objednávce, sloupec v adminu, zobrazení v e-mailech a na Děkujeme stránce
- Hromadný **CSV export pro Podání Online**
- **HPOS** ready, kompatibilní s Cart/Checkout blocks
- **Diagnostický mód** (`WP_DEBUG`) – do konzole se logují přijaté `postMessage` payloady widgetu
- Připraveno pro **i18n** (`.pot` šablona v `languages/`)

## Instalace

1. Stáhněte ZIP nebo naklonujte repozitář do `wp-content/plugins/balikovna-woocommerce/`.
2. Aktivujte plugin v admin WordPress.
3. WooCommerce → Nastavení → Doprava → vyberte zónu → Přidat dopravu → zvolte Balíkovna / Balík Do ruky / Balík Na poštu.

## Filtry pro vývojáře

| Filtr | Popis |
|---|---|
| `balikovna_wc_widget_url` | URL widgetu výběru pobočky |
| `balikovna_wc_widget_phone` | `true` = zobrazit pole pro telefon ve widgetu (default `false`) |
| `balikovna_wc_export_headers` | Hlavičky CSV exportu |
| `balikovna_wc_export_row` | Úprava řádku exportu (`$row, $order, $point`) |
| `balikovna_wc_cod_methods` | Seznam payment method ID považovaných za dobírku |
| `balikovna_wc_services` | Konfigurace dostupných služeb ČP |

## Roadmap

- [ ] Vlastní Leaflet mapa nad veřejným ČP REST API (fallback pro CSP/blokátory iframu)
- [ ] B2B API České pošty – automatické vytváření zásilek, tisk štítků, tracking
- [ ] Tisk PDF štítků z administrace
- [ ] Notifikace o změně stavu zásilky

## Licence

GPL-2.0-or-later. Loga České pošty / Balíkovny jsou majetkem Česká pošta, s.p.
