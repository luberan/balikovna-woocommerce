# Balíkovna for WooCommerce

[![CI](https://github.com/luberan/balikovna-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/luberan/balikovna-woocommerce/actions/workflows/ci.yml)
[![Latest Release](https://badgen.net/github/release/luberan/balikovna-woocommerce/stable)](https://github.com/luberan/balikovna-woocommerce/releases/latest)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

Open-source WooCommerce plugin pro integraci služeb **České pošty** – Balíkovna, Balík na adresu, Balík Do ruky a Balík Na poštu.

Komunikace s výdejními místy probíhá přes oficiální widget České pošty (`b2c.cpost.cz/locations`). Plugin nastavuje stabilní `messageId=pickerResult` a pro zpětnou kompatibilitu přijímá také historickou hodnotu `pickResult`.

## Funkce

- Čtyři shipping metody:
  - **Balíkovna** – výdejní místa včetně partnerských míst a samoobslužných boxů
  - **Balík na adresu (Balíkovna)** – doručení produktu Balíkovna na adresu
  - **Balík Do ruky** – doručení na adresu
  - **Balík Na poštu** – doručení na vybranou pobočku České pošty
- **Výběr výdejního místa** přes oficiální widget v přístupném modálním okně (parametry `type=BALIKOVNY|POST_OFFICE`, `skipLocation=false`, volitelně `phone=true`); vrácený telefon doplní chybějící `billing_phone`
- Serverové ověření ID, typu a kanonických údajů pobočky proti veřejně dostupnému JSON seznamu používanému widgetem ČP
- Samostatný výběr pro každý WooCommerce shipping package a přesnou instanci shipping metody
- Podpora **klasického checkoutu** (`wp-admin → WooCommerce → checkout shortcode`) i **Block Checkoutu** (Store API extension)
- **Cenotvorba** per metoda: fixní cena / podle hmotnosti (tabulka `max_kg|cena`) / volitelný práh „zdarma od“ z mezisoučtu celého košíku napříč shipping packages
- Produkty Balíkovna se nabízejí jen pro CZ zásilky do 15 kg a 50 × 50 × 50 cm; hmotnost a rozměry musí být vyplněné u všech produktů v balíku
- Checkout pro Balíkovnu vyžaduje platný e-mail a české mobilní číslo s předvolbou `+420` nebo `00420`
- **Kódy služeb ČP** (CSV sloupec I) konfigurovatelné per metoda v admin nastavení
- Uložení místa k objednávce, sloupec v přehledu objednávek, zobrazení v e-mailech a na stránce „Děkujeme"
- Hromadný **CSV export pro Podání Online** – jeden řádek pro každou zásilku, formát sloupců A–O, Windows-1250, středník
  - Pro typ NB (Balíkovna): adresa `Balíkova` + ID balíkovny v PSČ dle pokynů ČP
  - Pro typ NP (Balík Na poštu): adresa, PSČ a město vybrané pošty
  - Hodnota obsahu a hmotnost se ukládají samostatně pro každý shipping package; neúplná objednávka zastaví celý export
  - Finanční částky se exportují pouze v CZK, dobírka v celých korunách a jen na první zásilce objednávky
  - Import v Podání Online musí být nakonfigurovaný pro uvedené sloupce A–O a kódy odpovídající smlouvě odesílatele
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
   - **Zdarma od částky** (volitelně, vyhodnocuje se z celého košíku)
   - **Kódy služeb ČP** (např. `7+45+S+41` – dle označení ve vaší smlouvě s ČP; použije se ve sloupci I exportu)
   - U produktů používaných s Balíkovnou vyplňte hmotnost, délku, šířku a výšku; neúplný nebo nadlimitní balík se nenabídne.
3. Zákazníci ve checkoutu uvidí novou shipping metodu; u Balíkovny a Balíku Na poštu kliknou „Vybrat výdejní místo" → vybere v iframe widgetu → potvrdí objednávku.
4. V přehledu objednávek (WC → Objednávky) je hromadná akce **Export Balíkovna (CSV Podání Online)**.

## Filtry pro vývojáře

| Filtr | Popis |
|---|---|
| `balikovna_wc_services` | Konfigurace služeb včetně `countries`, `max_weight_kg`, `max_dimensions_cm` a požadavků na kontakt |
| `balikovna_wc_package_metrics` | Úprava vypočtené hmotnosti, rozměrů a objemu shipping package |
| `balikovna_wc_widget_url` | URL widgetu výběru pobočky (`$url, $type`) |
| `balikovna_wc_widget_phone` | `true` = zobrazit pole pro telefon; platný výsledek doplní chybějící `billing_phone` (default `false`) |
| `balikovna_wc_free_shipping_subtotal` | Úprava mezisoučtu celého košíku použitého pro práh dopravy zdarma |
| `balikovna_wc_export_headers` | Hlavičky CSV exportu |
| `balikovna_wc_export_row` | Úprava řádku exportu (`$row, $order, $point, $service_id, $shipping_item`) |
| `balikovna_wc_cod_methods` | Seznam payment method ID považovaných za dobírku (default `['cod']`) |
| `balikovna_wc_default_subject` | Default hodnota sloupce N v CSV (`F` = fyzická osoba, `P` = právnická) |
| `balikovna_wc_valid_recipient_phone` | Vlastní ověření normalizovaného telefonu příjemce |
| `balikovna_wc_recipient_contact_errors` | Úprava výsledných chyb kontaktu pro zvolené služby |
| `balikovna_wc_debug` | Vynucený diagnostický mód i bez `WP_DEBUG` |
| `balikovna_wc_point_validation_result` | Vlastní validační výsledek pobočky; `null` ponechá výchozí ověření |
| `balikovna_wc_points_directory` | Vlastní kanonický seznam poboček pro daný typ |
| `balikovna_wc_points_api_url` | URL API seznamu poboček |
| `balikovna_wc_points_cache_ttl` | Doba cache seznamu poboček v sekundách |
| `balikovna_wc_points_max_stale_age` | Maximální stáří nouzového seznamu při výpadku API (výchozí 30 dní) |

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

Automatické testy používají izolované WordPress/WooCommerce runtime stuby. Pokrývají regresní logiku pluginu, nenahrazují však browserový end-to-end test na skutečné instalaci WooCommerce, HPOS databázi ani autentizovaný import Podání Online.

## Externí služby a soukromí

Picker otevírá iframe `https://b2c.cpost.cz/locations/`, takže Česká pošta obdrží běžná HTTP metadata návštěvy. Polohu pro funkci „Moje poloha“ předá prohlížeč widgetu pouze po souhlasu zákazníka. Pokud správce zapne `phone=true`, zákazník zadává telefon přímo ve widgetu České pošty; plugin jej následně převezme a může uložit jako WooCommerce `billing_phone`. Server pluginu stahuje z téhož hostu veřejně dostupný JSON seznam poboček pro ověření výběru; do tohoto požadavku neposílá údaje zákazníka ani objednávky. Endpoint seznamu nemá zveřejněný verzovaný integrační kontrakt, proto lze zdroj nahradit filtry `balikovna_wc_points_api_url` nebo `balikovna_wc_points_directory` a nouzová data se přijímají nejvýše 30 dní. Podmínky zpracování provozovatele jsou na [webu České pošty](https://www.ceskaposta.cz/ochrana-osobnich-udaju).

## Rozsah a známá omezení

- Plugin aktuálně končí vytvořením objednávky a CSV pro Podání Online; zásilku automaticky nezakládá u České pošty.
- Není implementována Balíkovna plus, B2B-ZSK nAPI autentizace a podání, tisk štítků, tracking, storna, vratky ani podací reporty.
- Produktové prefixy a kódy doplňkových služeb závisejí na smlouvě odesílatele a musí odpovídat jeho konfiguraci Podání Online.
- Veřejně dostupný JSON seznam bodů funguje se současným widgetem, ale není publikovaný jako verzované veřejné API; plugin toto riziko omezuje validací, cache limitem a možností nahradit zdroj filtrem.

## Roadmap

- [ ] Balíkovna plus včetně jejích hmotnostních, rozměrových a doplňkových variant
- [ ] B2B-ZSK nAPI – HMAC autentizace, idempotentní vytvoření zásilek a uložení podacích kódů
- [ ] Tisk PDF štítků, tracking, storna a podací reporty
- [ ] Vratky a související B2B workflow
- [ ] Alternativní picker až nad stabilním a dokumentovaným číselníkem výdejních míst
- [ ] Reálné end-to-end testy Classic/Block Checkoutu, pay-for-order a HPOS
- [ ] Integrace s Block Checkoutem na úrovni logo/branding shipping metody

## Bezpečnost

Bezpečnostní problémy hlaste prosím privátně – viz [SECURITY.md](SECURITY.md).

## Licence

[GPL-3.0-or-later](LICENSE). Loga České pošty / Balíkovny jsou majetkem Česká pošta, s.p., a jsou použita v souladu s jejich veřejnou dostupností pro implementaci výdejních míst.
