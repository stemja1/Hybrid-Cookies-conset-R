=== Hybrid Cookies conset R+ ===
Contributors: stemja1
Tags: cookie, cookies, consent, gdpr, banner
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Consent banner pre WordPress s katalógom cookies a blokovaním skriptov pred súhlasom.

== Description ==

Hybrid Cookies conset R+ pridáva na stránku banner súhlasu s cookies, vedie centrálny
katalóg cookies podľa kategórií a blokuje nevyhnutné trackery dovtedy, kým
návštevník nevyjadrí súhlas.

Hlavné funkcie:

* Consent banner (dolná / horná pozícia, aj modálny režim)
* Kategórie cookies: nevyhnutné, funkčné, štatistické, marketingové
* Katalóg cookies s dobou platnosti a poskytovateľom
* Blokovanie skriptov cez vzory typu `google-analytics.com|statistics`
* Vynútiteľné blokovanie pred načítaním cez MutationObserver
* Anonymizovaný log súhlasov (IP bez posledného oktetu, hash návštevníka)
* REST API `hcc/v1/cookies` a `hcc/v1/settings`
* GDPR postoj: dáta v databáze zostávajú, kým sa explicitne nevyžiada ich zmazanie

== Installation ==

1. Nahrajte priečinok `hybrid-cookies-conset-r-plus` do `/wp-content/plugins/`.
2. Aktivujte plugin v menu Pluginy.
3. Nastavenia nájdete v menu **Cookies → Nastavenia**.

== Frequently Asked Questions ==

= Ako pridám vlastný skript do blokovania? =

V Nastaveniach pridajte vzor `substring-ulomok|kategória`, napríklad
`connect.facebook.net|marketing`. Skript sa načíta až po súhlase s danou kategóriou.

= Odstráni sa pri odinštalácii aj log súhlasov? =

Nie. Tabuľky a nastavenia zostávajú v databáze, aby ste nestratili doklady o súhlase.
V nastaveniach môžete zapnúť **Odstrániť dáta pri odinštalácii**.

= Je plugin GDPR compliant? =

Plugin pomáha so súhlasom a blokovaním, ale konečnú zodpovednosť za právny poriadok
a správne nastavenie kategórií má prevádzkovateľ webu. Pri analýze právnych dôsledkov sa
poraďte s právnikom.

== Changelog ==

= 0.1.0 =
* Prvotná kostra pluginu: banner, katalóg cookies, blokovanie skriptov, REST API, log súhlasov.

== Upgrade Notice ==

= 0.1.0 =
Prvé vydanie.