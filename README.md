# Hybrid Cookies conset R+

WordPress plugin: consent banner (cookie banner), centrálny katalóg cookies
a blokovanie trackovacích skriptov pred udeleným súhlasom.

## Stav projektu

Ide o **kostru**. Štruktúra, databázová schéma, admin menu, REST endpointy a
banner sú pripravené; funkčné detaily (autodetekcia cookies, GEO targeting,
dokumentácia GDPR) sú zatiaľ len `TODO` značky.

## Štruktúra

```
hybrid-cookies-conset-r-plus.php   Hlavný súbor, konštanty, bootstrap, hooky aktivácie
uninstall.php                      Čistenie pri odinštalácii (len ak je to zapnuté)
includes/
  class-hcc-plugin.php             Orchestrátor – načíta moduly
  class-hcc-activator.php          Schéma DB (hcc_cookies, hcc_consent_log) + predvoľby
  class-hcc-deactivator.php        Deaktivácia
  class-hcc-cookie-catalog.php     CRUD katalógu + predvolené cookies
  class-hcc-consent.php           Čítanie/zápis súhlasu, AJAX uloženie, log
  admin/
    class-hcc-admin.php            Menu, assety, odporúčania
    class-hcc-settings.php         Nastavenia + register_setting/sanitize
    class-hcc-cookie-list-table.php  WP_List_Table katalógu
    class-hcc-banner.php           Frontend banner + blokovací snippet
  api/
    class-hcc-rest-controller.php  REST `hcc/v1`
  helpers/
    class-hcc-helpers.php          Nastavenia, kategórie, anonymizácia IP
assets/css|js/                     Frontend a admin assety
languages/                         Preklady (POT)
```

## Databáza

| Tabuľka | Obsah |
| --- | --- |
| `{prefix}hcc_cookies` | Katalóg cookies: názov, slug, popis, poskytovateľ, kategória, doba platnosti |
| `{prefix}hcc_consent_log` | Anonymizované záznamy súhlasu (hash návštevníka, kategórie, anonymizovaná IP) |

## Inštalácia

Skopíruj priečinok do `wp-content/plugins/hybrid-cookies-conset-r-plus`
a aktivuj plugin. Prvé spustenie vytvorí tabuľky a naimportuje predvolený
katalóg cookies (Google Analytics, Meta Pixel, YouTube).

Vývojovo sa dá aktivovať cez symlink:

```bash
ln -s "$(pwd)" /path/to/wordpress/wp-content/plugins/hybrid-cookies-conset-r-plus
```

## Lokálne testovanie bez WordPressu

```bash
php -l hybrid-cookies-conset-r-plus.php
find . -name '*.php' -exec php -l {} \;
```

## Bezpečnosť a súkromie

* IP adresa sa pred zápisom anonymizuje (odstráni posledný oktet, IPv4/IPv6).
* Návštevník sa identifikuje iba SHA-256 hashom z IP, user-agenta a `AUTH_SALT`.
* Dáta zostávajú v databáze aj po odinštalácii, kým sa v nastaveniach výslovne
  nezaškrtne **Odstrániť dáta pri odinštalácii**.

## Licencia

GPL-2.0-or-later