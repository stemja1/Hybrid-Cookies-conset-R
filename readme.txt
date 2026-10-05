=== Hybrid Cookies conset R+ ===
Contributors: stemja1
Tags: cookie, cookies, consent, gdpr, ccpa, banner
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Consent banner pre WordPress s katalógom cookies a blokovaním trackovacích skriptov pred súhlasom. Bez závislosti na cloude.

== Description ==

Hybrid Cookies conset R+ pridáva na stránku consent banner, vedie katalóg cookies
podľa kategórií (nevyhnutné / funkčné / štatistické / marketingové) a blokuje
skripty tretích strán dovtedy, kým návštevník nevyjadrí súhlas.

Všetko beží na vašom serveri. Žiadny externý účet, žiadne cloud API, žiadne
dáta neopúšťajú vaš web.

= Vlastnosti =

* Consent banner (dolná / horná pozícia, aj modálny režim)
* Kategórie cookies podľa GDPR (nevyhnutné, funkčné, štatistické, marketingové)
* Katalóg cookies s dobou platnosti, poskytovateľom a účelom
* Automatické blokovanie skriptov bez manuálneho tagovania
* Lokálny log súhlasov (dôkaz pre GDPR), IP adresa sa ukladá iba ako hash
* REST API `hcc/v1` a vlastná capability `manage_hybrid_cookies`
* EN a SK preklady

= Integrácia do tém a pluginov =

* Filter `hcc_script_providers` na doplnenie vlastných poskytovateľov skriptov
* Filter `hcc_regions` na úpravu mapy krajina → región → zákon
* Filter `hcc_module_map` na pridanie alebo odobranie modulu
* Verejné JS API `window.hccConsent.*`

== Installation ==

1. Nahrajte priečinok `hybrid-cookies-conset-r-plus` do `/wp-content/plugins/`.
2. Aktivujte plugin v menu Pluginy.
3. Nastavenia nájdete v menu **Cookies → Nastavenia**.

Plugin má žiadne runtime závislosti — `composer install` nie je potrebný,
autoloader má zabudovaný fallback.

== Frequently Asked Questions ==

= Ako pridám vlastný skript do blokovania? =

Cez filter `hcc_script_providers` pridajte položku s patternom URL a kategóriou.
Podrobný príklad je v `README.md`.

= Odstránia sa pri odinštalácii aj log súhlasov? =

Nie, ak to výslovne nevyžiadate v nastaveniach. Log súhlasov je doklad o
splnení GDPR, takže jeho zmazanie bez súhlasu majiteľa webu by bolo protiprávne.

= Je plugin GDPR compliant? =

Plugin pomáha so súhlasom a blokovaním, ale konečnú zodpovednosť za právny
poriadok a správne zaradenie cookies do kategórií má prevádzkovateľ webu.

== Changelog ==

= 0.1.0 =
* Prvotná verzia. Infraštruktúra, databázová schéma (kategórie, cookies,
  bannery, log súhlasov), modules architektúra, REST základ a admin shell.
  Funkčné bannery, blokovanie a consent logy prichádzajú v ďalších verziách.

== Upgrade Notice ==

= 0.1.0 =
Prvé vydanie.