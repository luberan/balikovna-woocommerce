# Bezpečnostní politika

## Podporované verze

Aktivně podporována je vždy nejnovější minor verze série `1.x`. Bezpečnostní opravy starších verzí se neposkytují – aktualizujte na nejnovější vydání.

## Nahlášení zranitelnosti

**Neotvírejte prosím veřejné GitHub issue ani PR pro bezpečnostní problémy.**

Použijte jeden z těchto privátních kanálů:

1. **GitHub Private Vulnerability Reporting** (preferováno) –
   [Otevřít private security advisory](https://github.com/luberan/balikovna-woocommerce/security/advisories/new)
2. **E-mail** maintainerovi – kontakt najdete v profilu [@luberan](https://github.com/luberan).

V hlášení prosím uveďte:

- popis zranitelnosti a její potenciální dopad,
- verzi pluginu, WordPressu, WooCommerce a PHP,
- kroky k reprodukci nebo proof-of-concept,
- případně návrh opravy.

## Co očekávat

| Akce | Cílový čas |
| --- | --- |
| Potvrzení přijetí hlášení | do **3 pracovních dnů** |
| Prvotní triage a klasifikace | do **7 dnů** |
| Vydání opravy nebo mitigace | dle závažnosti (kritické co nejdříve, typicky do **30 dnů**) |
| Veřejné zveřejnění (CVE / advisory) | po vydání opravy, po dohodě s reportérem |

Reportéry s odpovědným disclosurem rádi uvedeme v changelogu / security advisory (pokud si přejí).

## Mimo rozsah

Tyto situace nepovažujeme za zranitelnosti pluginu (ale upozornění jsou vítána v běžných issues):

- chybná konfigurace shipping zón nebo cenotvorby na straně provozovatele e-shopu,
- problémy způsobené třetími pluginy modifikujícími checkout/Store API,
- DoS proti veřejnému widgetu České pošty (`b2c.cpost.cz`) – mimo naši kontrolu.
