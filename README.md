# Balíkovna for WooCommerce

[![CI](https://github.com/luberan/balikovna-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/luberan/balikovna-woocommerce/actions/workflows/ci.yml)
[![Latest Release](https://badgen.net/github/release/luberan/balikovna-woocommerce/stable)](https://github.com/luberan/balikovna-woocommerce/releases/latest)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

Open-source WooCommerce plugin pro integraci služeb **České pošty** – Balíkovna, Balík na adresu, Balík Do ruky a Balík Na poštu.

Komunikace s výdejními místy probíhá přes oficiální widget České pošty (`b2c.cpost.cz/locations`); formát `postMessage` payloadu (`message: 'pickResult'`, `point: { id, zip, name, … }`) byl potvrzen podporou ČP v květnu 2026.

## Funkce

- Čtyři shipping metody:
  - **Balíkovna** – výdejní místa (přes 3 500 poboček + AlzaBox / BOX OX / Penguin Box)
  - **Balík na adresu (Balíkovna)** – doručení produktu Balíkovna na adresu
  - **Balík Do ruky** – doručení na adresu
  - **Balík Na poštu** – doručení na vybranou pobočku České pošty
- **Výběr výdejního místa** přes oficiální widget v přístupném modálním okně (parametry `type=BALIKOVNY|POST_OFFICE`, `skipLocation=false`, volitelně `phone=true`)
- Serverové ověření ID, typu a kanonických údajů pobočky proti datům stejného API, které používá widget ČP
- Samostatný výběr pro každý WooCommerce shipping package a přesnou instanci shipping metody
- Plná kompatibilita s **klasickým checkoutem** (`wp-admin → WooCommerce → checkout shortcode`) i **Block Checkoutem** (Store API extension)
- **Cenotvorba** per metoda: fixní cena / podle hmotnosti (tabulka `max_kg|cena`) / volitelný práh „zdarma od"
- **Kódy služeb ČP** (CSV sloupec I) konfigurovatelné per metoda v admin nastavení
- Uložení místa k objednávce, sloupec v přehledu objednávek, zobrazení v e-mailech a na stránce „Děkujeme"
- Hromadný **CSV export pro Podání Online** – jeden řádek pro každou zásilku, formát sloupců A–O, Windows-1250, středník
  - Pro typ NB (Balíkovna): adresa `Balíkova` + ID balíkovny v PSČ dle pokynů ČP
  - Pro typ NP (Balík Na poštu): adresa, PSČ a město vybrané pošty
- **HPOS** ready (High-Performance Order Storage), kompatibilní s Cart/Checkout blocks
- **Diagnostický mód** (`WP_DEBUG` nebo filtr) – do konzole loguje přijaté `postMessage` payloady widgetu
- Logo služby ČP v shipping metodě (klasický checkout)
- **Automatické aktualizace** z GitHub Releases (knihovna [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker), MIT) – v admin Pluginy se nové verze zobrazují jako u pluginů z wordpress.org
- Připraveno pro **i18n** (`languages/balikovna-wc.pot`)

## Kompatibilita

| | Minimum | Testováno |
|---|---|---|
| WordPress | 6.9 | 7.0 |
| WooCommerce | 10.8 | 10.8 |
| PHP | 7.4 | 8.5 |

## Instalace

### Z GitHub releasu (doporučeno)

1. Stáhněte `balikovna-woocommerce.zip` z [posledního releasu](https://github.com/luberan/balikovna-woocommerce/releases/latest).
   (Pozor – **ne** „Source code (zip)", ten má v názvu složky verzi a rozbije aktualizace.)
2. WordPress admin → Pluginy → Přidat nový → Nahrát plugin → vyberte ZIP → Instalovat → Aktivovat.

### Z gitu (pro vývoj)

```bash
cd wp-content/plugins
git clone https://github.com/luberan/balikovna-woocommerce.git
```

## Konfigurace

1. WooCommerce → Nastavení → Doprava → vyberte zónu → **Přidat dopravu** → zvolte jednu ze čtyř služeb ČP.
2. Editujte metodu a nastavte:
   - **Název** zobrazený zákazníkovi
   - **Typ ceny** (fixní / podle hmotnosti)
   - **Cena** nebo **váhová tabulka** (řádky `max_kg|cena`, např. `5|79`)
   - **Zdarma od částky** (volitelně)
   - **Kódy služeb ČP** (např. `7+45+S+41` – dle označení ve vaší smlouvě s ČP; použije se ve sloupci I exportu)
3. Zákazníci ve checkoutu uvidí novou shipping metodu; u Balíkovny a Balíku Na poštu kliknou „Vybrat výdejní místo" → vybere v iframe widgetu → potvrdí objednávku.
4. V přehledu objednávek (WC → Objednávky) je hromadná akce **Export Balíkovna (CSV Podání Online)**.

## Filtry pro vývojáře

| Filtr | Popis |
|---|---|
| `balikovna_wc_services` | Konfigurace dostupných služeb ČP (label, popis, `pickup`, `service_code`, logo) |
| `balikovna_wc_widget_url` | URL widgetu výběru pobočky (`$url, $type`) |
| `balikovna_wc_widget_phone` | `true` = zobrazit pole pro telefon ve widgetu (default `false`) |
| `balikovna_wc_export_headers` | Hlavičky CSV exportu |
| `balikovna_wc_export_row` | Úprava řádku exportu (`$row, $order, $point, $service_id, $shipping_item`) |
| `balikovna_wc_cod_methods` | Seznam payment method ID považovaných za dobírku (default `['cod']`) |
| `balikovna_wc_default_subject` | Default hodnota sloupce N v CSV (`F` = fyzická osoba, `P` = právnická) |
| `balikovna_wc_debug` | Vynucený diagnostický mód i bez `WP_DEBUG` |
| `balikovna_wc_point_validation_result` | Vlastní validační výsledek pobočky; `null` ponechá výchozí ověření |
| `balikovna_wc_points_directory` | Vlastní kanonický seznam poboček pro daný typ |
| `balikovna_wc_points_api_url` | URL API seznamu poboček |
| `balikovna_wc_points_cache_ttl` | Doba cache seznamu poboček v sekundách |

## Vývoj

Repo používá [Conventional Commits](https://www.conventionalcommits.org/) a [release-please](https://github.com/googleapis/release-please) pro automatické generování changelogu a bumpování verze. Detaily v [CONTRIBUTING.md](CONTRIBUTING.md).

Po `composer install` jsou dostupné příkazy:

```bash
composer test   # regresní a metadata testy
composer lint   # WordPress Core, security sniffs a PHP 7.4+ kompatibilita
composer pot    # regenerace languages/balikovna-wc.pot
composer build  # čistý staging do build/balikovna-woocommerce
```

CI běží PHP lint matrix 7.4–8.5 a blokující QA na PHP 7.4 i 8.5. Release ZIP se skládá stejným reprodukovatelným build skriptem.

## Externí služby a soukromí

Picker otevírá iframe `https://b2c.cpost.cz/locations/`, takže Česká pošta obdrží běžná HTTP metadata návštěvy. Polohu pro funkci „Moje poloha“ předá prohlížeč widgetu pouze po souhlasu zákazníka. Server pluginu stahuje z téhož hostu veřejný seznam poboček pro ověření výběru; do tohoto požadavku neposílá údaje zákazníka ani objednávky. Podmínky zpracování provozovatele jsou na [webu České pošty](https://www.ceskaposta.cz/ochrana-osobnich-udaju).

## Roadmap

- [ ] B2B API České pošty – automatické vytváření zásilek (bez ručního importu CSV)
- [ ] Tisk PDF štítků z admin rozhraní
- [ ] Tracking zásilek + notifikace o změně stavu
- [ ] Vlastní Leaflet mapa nad `napostu.ceskaposta.cz/vystupy/balikovny.xml` (fallback pro CSP/blokátory iframu)
- [ ] Integrace s Block Checkoutem na úrovni logo/branding shipping metody

## Bezpečnost

Bezpečnostní problémy hlaste prosím privátně – viz [SECURITY.md](SECURITY.md).

## Licence

[GPL-3.0-or-later](LICENSE). Loga České pošty / Balíkovny jsou majetkem Česká pošta, s.p., a jsou použita v souladu s jejich veřejnou dostupností pro implementaci výdejních míst.
