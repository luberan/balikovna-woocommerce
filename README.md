# Balíkovna for WooCommerce

[![CI](https://github.com/luberan/balikovna-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/luberan/balikovna-woocommerce/actions/workflows/ci.yml)
[![Latest Release](https://badgen.net/github/release/luberan/balikovna-woocommerce/stable)](https://github.com/luberan/balikovna-woocommerce/releases/latest)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

Open-source WooCommerce plugin pro integraci služeb **České pošty** – Balíkovna, Balík na adresu, Balíkovna plus, Balík Do ruky a Balík Na poštu.

Komunikace s výdejními místy probíhá přes oficiální widget České pošty (`b2c.cpost.cz/locations`). Plugin nastavuje stabilní `messageId=pickerResult` a pro zpětnou kompatibilitu přijímá také historickou hodnotu `pickResult`.

## Funkce

- Pět shipping metod:
  - **Balíkovna** – výdejní místa včetně partnerských míst a samoobslužných boxů
  - **Balík na adresu (Balíkovna)** – doručení produktu Balíkovna na adresu
  - **Balíkovna plus** – větší a těžší zásilky na adresu; standardně do 31,5 kg, smluvně do 50 kg
  - **Balík Do ruky** – doručení na adresu
  - **Balík Na poštu** – doručení na vybranou pobočku České pošty
- **Výběr výdejního místa** přes oficiální widget v přístupném modálním okně (parametry `type=BALIKOVNY|POST_OFFICE`, `skipLocation=false`, volitelně `phone=true`); vrácený telefon doplní chybějící `billing_phone`
- Serverové ověření ID, typu a kanonických údajů pobočky proti veřejně dostupnému JSON seznamu používanému widgetem ČP
- Samostatný výběr pro každý WooCommerce shipping package a přesnou instanci shipping metody
- Podpora **klasického checkoutu** (`wp-admin → WooCommerce → checkout shortcode`) i **Block Checkoutu** (Store API extension)
- **Cenotvorba** per metoda: fixní cena / podle hmotnosti (tabulka `max_kg|cena`) / volitelný práh „zdarma od“ z mezisoučtu celého košíku napříč shipping packages
- Produkty Balíkovna se nabízejí jen pro CZ zásilky do 15 kg a 50 × 50 × 50 cm. Známé parametry se vždy kontrolují; u fixní ceny chybějící katalogový údaj sazbu neskryje, váhová cenotvorba však vyžaduje vyplněnou hmotnost.
- Checkout pro Balíkovnu vyžaduje platný e-mail a české mobilní číslo s předvolbou `+420` nebo `00420`
- **Kódy služeb ČP** (CSV sloupec I) konfigurovatelné per metoda v admin nastavení
- Uložení místa k objednávce, sloupec v přehledu objednávek, zobrazení v e-mailech a na stránce „Děkujeme"
- Ruční **podací číslo** pro každou zásilku České pošty; odkaz na oficiální Track & Trace v administraci, zákaznickém e-mailu a detailu objednávky
- Automatická **synchronizace stavů zásilek** přes oficiální B2B-ZSK/CIS nAPI přibližně každých 30 minut:
  - využívá stávající podací číslo na konkrétním shipping itemu a podporuje všech pět shipping metod,
  - stav, čas události a čas poslední kontroly ukládá samostatně ke každé zásilce,
  - dynamicky načítá číselník agregovaných stavů přes `statusesOverview` a bezpečně zachovává poslední funkční cache,
  - umí volitelně mapovat stabilní kódy ČP na libovolné stavy z `wc_get_order_statuses()`, včetně stavů registrovaných jiným pluginem,
  - mění stav přes standardní `WC_Order::update_status()`, takže existující WooCommerce e-maily reagují přirozeně a plugin je neduplikuje,
  - u více zásilek postupuje konzervativně; objednávku dokončí až po doručení všech sledovaných zásilek.
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

1. WooCommerce → Nastavení → Doprava → vyberte zónu → **Přidat dopravu** → zvolte jednu z pěti služeb ČP.
2. Editujte metodu a nastavte:
   - **Název** zobrazený zákazníkovi
   - **Typ ceny** (fixní / podle hmotnosti)
   - **Cena** nebo **váhová tabulka** (řádky `max_kg|cena`, např. `5|79`)
   - **Zdarma od částky** (volitelně, vyhodnocuje se z celého košíku)
   - **Kódy služeb ČP** (např. `7+45+S+41` – dle označení ve vaší smlouvě s ČP; použije se ve sloupci I exportu)
    - U produktů používaných s Balíkovnou vyplňte hmotnost, délku, šířku a výšku pro úplnou automatickou kontrolu. Známý nadlimitní parametr dopravu vždy skryje; neznámý údaj ji u fixní ceny sám o sobě neskryje.
    - U Balíkovny plus vyberte smluvní **typ zásilky DR/DV/DE** a limit 31,5 nebo 50 kg. Limit 50 kg zapněte pouze při sjednané smlouvě; žádná strana nesmí překročit 200 cm a součet tří rozměrů 300 cm.
3. Zákazníci ve checkoutu uvidí novou shipping metodu; u Balíkovny a Balíku Na poštu kliknou „Vybrat výdejní místo" → vybere v iframe widgetu → potvrdí objednávku.
4. V detailu objednávky lze ke každému shipping itemu České pošty zadat **podací číslo**. Uloží se před změnou stavu objednávky, takže jej následně odeslaný e-mail obsahuje spolu s odkazem Track & Trace.
5. Pro automatické stavy otevřete **WooCommerce → Nastavení → Doprava → Sledování stavu zásilek**:
  - v uživatelské aplikaci Pošta Online si vygenerujte `Api-Token` a `secretKey` pro B2B nAPI,
  - zvolte produkční nebo oficiální testovací prostředí, údaje uložte a klikněte na **Otestovat připojení**,
  - zapněte sledování, nastavte počet objednávek na běh (výchozí 100), stáří objednávek (výchozí 14 dní) a stavy objednávek, které mají být sledovány,
  - zvolte agregované stavy ČP, které se mají dál dotazovat; neznámý nově pozorovaný stav se dál kontroluje, dokud konfiguraci znovu neuložíte,
  - volitelně zapněte změny stavů objednávek a nastavte mapování **Česká pošta status → WooCommerce status**.
6. Doporučené stavy `wc-shipped` a `wc-ready-pickup` plugin sám neregistruje. Pokud existují, objeví se automaticky ve volbách a bezpečných výchozích mapováních. Totéž platí pro jakýkoli jiný stav vrácený `wc_get_order_statuses()`.
7. V přehledu objednávek (WC → Objednávky) je hromadná akce **Export Balíkovna (CSV Podání Online)**.

Výchozí automatické mapování používá pouze jednoznačné agregované významy z aktuálního číselníku: `PODÁNO` a `V PŘEPRAVĚ` → `wc-shipped` (pokud existuje), `ULOŽENO` → `wc-ready-pickup` (pokud existuje) a `DORUČENO` → `wc-completed`. Předaná/zpracovaná data, vratky a neznámé stavy výchozí změnu objednávky neprovádějí. Správce může mapování změnit.

## Filtry pro vývojáře

| Filtr | Popis |
|---|---|
| `balikovna_wc_services` | Konfigurace služeb včetně `countries`, `max_weight_kg`, `max_dimensions_cm` a požadavků na kontakt |
| `balikovna_wc_package_metrics` | Úprava vypočtené hmotnosti, rozměrů a objemu shipping package |
| `balikovna_wc_require_complete_package_metrics` | `true` = skrýt Balíkovnu také při chybějící hmotnosti nebo rozměru (výchozí `false`) |
| `balikovna_wc_widget_url` | URL widgetu výběru pobočky (`$url, $type`) |
| `balikovna_wc_widget_phone` | `true` = zobrazit pole pro telefon; platný výsledek doplní chybějící `billing_phone` (default `false`) |
| `balikovna_wc_free_shipping_subtotal` | Úprava mezisoučtu celého košíku použitého pro práh dopravy zdarma |
| `balikovna_wc_export_headers` | Hlavičky CSV exportu |
| `balikovna_wc_export_row` | Úprava řádku exportu (`$row, $order, $point, $service_id, $shipping_item`) |
| `balikovna_wc_cod_methods` | Seznam payment method ID považovaných za dobírku (default `['cod']`) |
| `balikovna_wc_default_subject` | Default hodnota sloupce N v CSV (`F` = fyzická osoba, `P` = právnická) |
| `balikovna_wc_valid_recipient_phone` | Vlastní ověření normalizovaného telefonu příjemce |
| `balikovna_wc_recipient_contact_errors` | Úprava výsledných chyb kontaktu pro zvolené služby |
| `balikovna_wc_tracking_url` | Úprava Track & Trace URL (`$url, $tracking_number`) |
| `balikovna_wc_tracking_interval` | Interval opakované Action Scheduler akce v sekundách (výchozí 30 minut, bezpečnostní minimum 15 minut) |
| `balikovna_wc_tracking_batch_size` | Úprava maximálního počtu objednávek pro aktuální běh synchronizace |
| `balikovna_wc_tracking_scan_pages` | Maximální počet stránek kandidátů prohledaných v jednom běhu (výchozí 10, maximum 100) |
| `balikovna_wc_tracking_shipment_eligible` | Poslední rozhodnutí, zda se konkrétní shipping item smí synchronizovat (`$eligible, $shipment, $order, $settings`) |
| `balikovna_wc_parsed_carrier_status` | Úprava bezpečně parsovaného objektu `Shipment_Status` před uložením |
| `balikovna_wc_carrier_status_mapping` | Úprava cílového stavu objednávky pro konkrétní kód a zásilku |
| `balikovna_wc_status_dictionary_ttl` | Doba platnosti cache `statusesOverview` (výchozí 24 hodin) |
| `balikovna_wc_debug` | Vynucený diagnostický mód i bez `WP_DEBUG` |
| `balikovna_wc_point_validation_result` | Vlastní validační výsledek pobočky; `null` ponechá výchozí ověření |
| `balikovna_wc_points_directory` | Vlastní kanonický seznam poboček pro daný typ |
| `balikovna_wc_points_api_url` | URL API seznamu poboček |
| `balikovna_wc_points_cache_ttl` | Doba cache seznamu poboček v sekundách |
| `balikovna_wc_points_max_stale_age` | Maximální stáří nouzového seznamu při výpadku API (výchozí 30 dní) |

Akce `balikovna_wc_shipment_status_changed` se spustí po skutečné změně kódu zásilky. Akce `balikovna_wc_order_status_mapped` se spustí po provedené standardní změně stavu objednávky.

## Česká pošta nAPI

Implementace vychází z aktuálních oficiálních specifikací [B2B-ZSKService 1.13.0](https://www.postaonline.cz/dokumentaceapi/b2b/zsk), [B2B-CISService 1.2.0](https://www.postaonline.cz/dokumentaceapi/b2b/cis) a dokumentu [nAPI – nejčastější dotazy a vyskytující se chyby](https://www.ceskaposta.cz/documents/d/guest/napi-nejcastejsi_dotazy_a_vyskytujici_se_chyby-pdf-2).

Použité read-only operace:

- ZSK `GET /parcelStatuses/current/idParcel/{idParcel}` (`statusInfo`) – aktuální agregovaný stav jedné zásilky,
- CIS `GET /statusesOverview` – číselník kombinací `status`/`reason` a jejich agregovaného názvu.

Oba GET požadavky používají hlavičky `Api-Token`, `Authorization-Timestamp` a `Authorization`. Plugin vygeneruje UUIDv4 `nonce`; pro požadavek bez těla podepisuje řetězec `;Authorization-Timestamp;nonce` pomocí HMAC-SHA256 a `secretKey`, výsledek Base64 vloží do `Authorization: CP-HMAC-SHA256 nonce="…", signature="…"`. `secretKey` požadavek nikdy neobsahuje. Časové razítko je UTC Unix timestamp; specifikace toleruje nejvýše 60 sekund a nepovoluje budoucí čas.

Použité hosty:

- produkce: `https://b2b.postaonline.cz:444/restservices/ZSKService/v1` a `https://b2b.postaonline.cz:444/restservices/CISService/v1`,
- test: `https://b2b-test.postaonline.cz:444/restservices/ZSKService/v1` a `https://b2b-test.postaonline.cz:444/restservices/CISService/v1`.

Oficiální FAQ doporučuje aktuální stav kontrolovat přibližně každých 30 minut. Samotné FAQ neuvádí číselný limit volání; plugin přesto omezuje počet objednávek na běh, neopakuje chyby agresivně a po globální chybě autentizace nebo omezení zastaví celý batch.

## Diagnostika synchronizace

- Na stránce nastavení je čas poslední úspěšné synchronizace, poslední globální chyba a příští naplánovaný běh.
- Ruční tlačítko **Synchronizovat nyní** spouští stejný omezený batch jako plánovaná akce a používá stejný zámek.
- Ve **WooCommerce → Stav → Naplánované akce** hledejte hook `balikovna_wc_sync_shipment_statuses` ve skupině `balikovna-woocommerce`.
- Do WooCommerce logu se zapisují sanitizované dočasné chyby pod zdrojem `balikovna-woocommerce-tracking`; podací čísla, autentizační hlavičky ani tajný klíč se nelogují.
- Při `DISABLE_WP_CRON=true` musí provozovatel pravidelně volat standardní `wp-cron.php`; plugin nepřidává vlastní veřejný cron endpoint.

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

Picker otevírá iframe `https://b2c.cpost.cz/locations/`, takže Česká pošta obdrží běžná HTTP metadata návštěvy. Polohu pro funkci „Moje poloha“ předá prohlížeč widgetu pouze po souhlasu zákazníka. Pokud správce zapne `phone=true`, zákazník zadává telefon přímo ve widgetu České pošty; plugin jej následně převezme a může uložit jako WooCommerce `billing_phone`. Server pluginu stahuje z téhož hostu veřejně dostupný JSON seznam poboček pro ověření výběru; do tohoto požadavku neposílá údaje zákazníka ani objednávky. Endpoint seznamu nemá zveřejněný verzovaný integrační kontrakt, proto lze zdroj nahradit filtry `balikovna_wc_points_api_url` nebo `balikovna_wc_points_directory` a nouzová data se přijímají nejvýše 30 dní.

Při zapnutém nAPI sledování server e-shopu posílá na výše uvedený pevný ZSK host pouze podací číslo potřebné pro `statusInfo` a autentizační hlavičky (`Api-Token`, timestamp, nonce a podpis). `secretKey` zůstává uložen v nastavení WordPressu a používá se lokálně jako HMAC klíč. CIS `statusesOverview` neposílá podací číslo ani údaje zákazníka. Plugin ukládá jen aktuální kód/název stavu, čas události a časy kontroly ke konkrétnímu WooCommerce shipping itemu; neukládá kompletní API odpověď. Tyto diagnostické údaje se zákazníkům nezobrazují. Podmínky zpracování provozovatele jsou na [webu České pošty](https://www.ceskaposta.cz/ochrana-osobnich-udaju).

## Rozsah a známá omezení

- Plugin aktuálně končí vytvořením objednávky a CSV pro Podání Online; zásilku automaticky nezakládá u České pošty.
- nAPI integrace je pouze pro čtení stavů. Není implementováno automatické podání/vytvoření zásilky, tisk štítků, storno, vratka, svoz, manifest ani jiná write operace.
- Synchronizace začíná až po ručním nebo jiným systémem provedeném uložení platného podacího čísla ke shipping itemu a vyžaduje smluvní B2B nAPI přístup České pošty.
- Vzácné nebo nově přidané agregované stavy bez jednoznačně zveřejněné sémantiky zůstávají bez výchozí změny objednávky a nadále se kontrolují. Správce je může namapovat po ověření významu.
- Běh závisí na Action Scheduleru/WP-Cronu. Na webu bez návštěv je nutné spouštět `wp-cron.php` externě.
- Produktové prefixy a kódy doplňkových služeb závisejí na smlouvě odesílatele a musí odpovídat jeho konfiguraci Podání Online.
- Veřejně dostupný JSON seznam bodů funguje se současným widgetem, ale není publikovaný jako verzované veřejné API; plugin toto riziko omezuje validací, cache limitem a možností nahradit zdroj filtrem.
- Plugin neregistruje `wc-shipped`, `wc-ready-pickup` ani vlastní zákaznické e-mailové šablony. Použije je pouze tehdy, když je poskytuje jiný plugin nebo vlastní kód webu.

## Roadmap

- [ ] B2B-ZSK nAPI – idempotentní vytvoření zásilek a uložení přidělených podacích kódů
- [ ] Tisk PDF štítků, storna, svozy, manifesty a podací reporty
- [ ] Vratky a související B2B workflow
- [ ] Alternativní picker až nad stabilním a dokumentovaným číselníkem výdejních míst
- [ ] Reálné end-to-end testy Classic/Block Checkoutu, pay-for-order a HPOS
- [ ] Integrace s Block Checkoutem na úrovni logo/branding shipping metody

## Bezpečnost

Bezpečnostní problémy hlaste prosím privátně – viz [SECURITY.md](SECURITY.md).

## Licence

[GPL-3.0-or-later](LICENSE). Loga České pošty / Balíkovny jsou majetkem Česká pošta, s.p., a jsou použita v souladu s jejich veřejnou dostupností pro implementaci výdejních míst.
