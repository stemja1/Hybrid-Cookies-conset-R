# Hybrid Cookies conset R+

WordPress plugin: consent banner (cookie banner), katalóg cookies a blokovanie
trackovacích skriptov pred udeleným súhlasom. GDPR/CCPA.

**Stav:** `0.1.0` — MVP fáza 1, pozri [SESSIONS.md](SESSIONS.md) pre plán.

Plugin je **self-hosted**. Žiadny cloud účet, žiadne externé API, žiadne dáta
neopúšťajú server.

## Požiadavky

| | |
| --- | --- |
| PHP | 8.1+ |
| WordPress | 6.4+ |
| Composer | voliteľný — plugin nemá runtime závislosti |

## Inštalácia

Skopíruj priečinok do `wp-content/plugins/hybrid-cookies-conset-r-plus`
a aktivuj v menu **Pluginy**.

Pre vývoj je najlepšie symlink, aby zmeny v kóde boli ihneď vidieť vo WPe:

```bash
ln -s "$(pwd)" /path/to/wordpress/wp-content/plugins/hybrid-cookies-conset-r-plus
```

Autoloading: plugin najprv skúsi `vendor/autoload.php` (Composer). Ak súbor
neexistuje, použije `includes/autoload-fallback.php` — jednoduchý PSR-4
autoloader mapujúci `HCC\` na `includes/`, `HCC\Modules\` na `modules/`
a `HCC\Admin\` na `admin/`. Preto sa plugin dá nainštalovať aj cez FTP
bez `composer install`.

## Štruktúra

```
hybrid-cookies-conset-r-plus.php   Konštanty, kontrola požiadaviek, bootstrap
uninstall.php                      Čistenie (iba ak je to v nastaveniach zapnuté)

includes/
  Plugin.php                       Singleton, životný cyklus
  Abstract_Module.php              Základná trieda modulov
  Module_Registry.php              Načítanie, zoradenie podľa priority, prístup
  Options.php                      Typovaný wrapper nad wp_options + schéma
  Tables.php                       Názvy tabuliek a SQL schéma
  Version.php                      Jediný zdroj pravdy o verzii
  Capabilities.php                 Capability manage_hybrid_cookies
  Activator.php                    dbDelta, seedovanie
  Deactivator.php                  Čistenie transientov
  I18n.php                         Načítanie prekladov, EN + SK
  autoload-fallback.php            PSR-4 fallback bez Composera
  helpers/autoload.php             Bootstrap pre aktiváciu/deaktiváciu

admin/
  Admin_Menu.php                   wp-admin menu, výber React/fallback
  Rest_Controller.php              CRUD REST API
  Fallback_Pages.php               PHP obrazovky pre prípad, že React build chýba
  ui/src/                          React zdrojový kód
  ui/dist/                         Build — commitnutý, pozri nižšie

public/
  css/banner-critical.css           Inline v `<head>` — bez FOUC
  css/banner.css                   Zvyšok štýlov bannera
  js/consent-banner.js             Hydrátcia bannera (nevytvára ho od nuly)

modules/
  Categories/                      Kategórie súhlasu — repository, seeder
  Cookies/                         Katalóg cookies — repository, seeder
  Blocker/
    Script_Catalog.php             Katalóg poskytovateľov skriptov
    Blocker_Config.php             Konfigurácia pre frontend
    Blocker_Module.php             Enqueue do `<head>` s prioritou 0
    data/known-cookies.json        63 URL patternov
    js/blocker.js                  Auto-blocking (MutationObserver)
  Consent/                         Cookie, recorder, región, REST controller
  Banner/
    Banner_Repository.php          Konfigurácie, verzionovanie, sanitizácia
    Banner_Renderer.php            SSR — vykreslí banner na serveri
    Frontend_Loader.php            Enqueue, critical CSS, shortcodes
    templates/{default,bar,box}.php  Tri layouty bannera

config/                            regióny, predvolené kategórie
assets/css/                        Admin štýly
admin/ui/src/                      React zdrojový kód (@wordpress/scripts)
languages/                         Preklady (POT, PO, MO)
tests/                             PHPUnit cez wp-phpunit
```

## Pridanie modulu

Nový modul vytvoríš pridaním triedy do `modules/<Názov>/<Názov>_Module.php`
a zápisom do mapy v `Module_Registry::get_module_map()` (alebo cez filter
`hcc_module_map`). Modul musí dediť z `HCC\Abstract_Module`.

```php
namespace HCC\Modules\Scan;

use HCC\Abstract_Module;

class Scan_Module extends Abstract_Module {

	protected int $priority = 40;

	public function get_slug(): string {
		return 'scan';
	}

	public function get_label(): string {
		return __( 'Scanner cookies', 'hybrid-cookies-conset-r-plus' );
	}

	public function register(): void {
		add_action( 'hcc_loaded', array( $this, 'run' ) );
	}
}
```

Priority (`$priority`) určuje poradie načítania — čím nižšie, tým skôr.

## Hooky

### Akcie

| Hook | Popis |
| --- | --- |
| `hcc_loaded` | Všetky moduly sú načítané |
| `hcc_activated` | Plugin bol aktivovaný |
| `hcc_deactivated` | Plugin bol deaktivovaný |
| `hcc_consent_recorded` | Návštevník udelil súhlas (uuid, riadok v tabuľke, payload) |
| `hcc_frontend_consent_saved` | Súhlas uložený z frontendu (payload, uuid) |
| `hcc_region_resolved` | Určený región (efektívny, pred filtrom, krajina) |
| `hcc_cookie_updated` | Katalóg cookies sa zmenil |
| `hcc_banner_saved` | Konfigurácia bannera sa uložila |

### Filtre

| Hook | Popis |
| --- | --- |
| `hcc_module_map` | Zoznam modulov (slug → trieda) |
| `hcc_skip_module` | Preskočiť modul pri načítaní |
| `hcc_options_schema` | Schéma nastavení |
| `hcc_script_providers` | Zoznam poskytovateľov skriptov a ich kategórií |
| `hcc_regions` | Mapa krajina → región → typ súhlasu |
| `hcc_region_definitions` | Definície regiónov |
| `hcc_region_resolved` | Prepísanie určeného regiónu |
| `hcc_geoip_country` | Kód krajiny z GeoIP (predvolene sa nepoužíva) |
| `hcc_region_to_country` | Namapovanie regiónu na krajinu pre režim `manual` |
| `hcc_consent_expiry_days` | Doba platnosti cookie súhlasu |
| `hcc_blocker_config` | Konfigurácia frontendu (cookie, granted, patterns, nonce) |
| `hcc_blocker_debug` | `true` zapne debug výpis do konzoly |
| `hcc_locale` | Prepnutie locale |

Príklad doplnenia vlastného poskytovateľa:

```php
add_filter(
	'hcc_script_providers',
	static function ( array $providers ): array {
		$providers[] = array(
			'pattern'  => 'cdn.mojskript.sk',
			'provider' => 'Môj skript',
			'category' => 'marketing',
		);

		return $providers;
	}
);
```

## Konfigurácia

### Regióny a zákony

`config/regions.php` mapuje krajinu na región, región určuje typ súhlasu
(`optin` / `optout`) a zákon. Predvolene je pokrytých 40 krajín v 8 regiónoch:

| Región | Krajiny (výber) | Typ súhlasu | Zákon |
| --- | --- | --- | --- |
| `eu` | EÚ 27 + SK, DE, FR… | `optin` | GDPR |
| `uk` | GB | `optin` | UK GDPR |
| `ch` | CH | `optin` | FADP |
| `us` | US | `optout` | CCPA |
| `br` | BR | `optin` | LGPD |
| `ca` | CA | `optin` | PIPEDA |
| `jp` | JP | `optin` | APPI |
| `au` | AU | `optin` | Privacy Act |
| `generic` | všetky ostatné | `optin` | GENERIC |

Rozlíšenie `optin` / `optout` je právne podstatné: v EÚ musí návštevník
súhlas **udeliť**, v USA stačí, že ho **neodmietne**. Banner musí byť
nastavený podľa regiónu, nie univerzálne.

Mapa sa dá meniť cez filter `hcc_regions`, regióny cez
`hcc_region_definitions`.

### Kategórie

`config/default-categories.json` obsahuje štyri kategórie v oboch jazykoch
naraz (`en_US`, `sk_SK`), takže seedovanie funguje bez ohľadu na jazyk webu.
Kategória `necessary` je chránená — nedá sa prehodiť na dobrovoľnú ani zmazať.

### Katalóg cookies

`config/default-cookies.json` obsahuje 45 najbežnejších cookies
(Google Analytics, Meta Pixel, YouTube, Hotjar, TikTok, Stripe…) a
`modules/Blocker/data/known-cookies.json` 63 URL patternov poskytovateľov
skriptov.

`Script_Catalog` pri vyhľadávaní vyberá **najdlhší** zhodný pattern, takže
`googletagmanager.com/ns.html` vyhráva pred `googletagmanager.com`. Prázdne
patterny a neznáme kategórie sú zahodené — pattern `''` by zablokoval celý web
a neznáma kategória by spôsobila, že by sa cookie nikdy neodblokovala.

Katalóg je cachovaný v transiente `hcc_script_providers` na 12 hodín.

## Databáza

Všetky tabuľky používajú prefix `{wp_prefix}hcc_` a sú vytvorené cez `dbDelta()`.

| Tabuľka | Obsah |
| --- | --- |
| `hcc_categories` | Kategórie súhlasu: slug, názov, popis, `is_necessary`, `sell_personal_data`, poradie |
| `hcc_cookies` | Katalóg cookies: kategória, názov, poskytovateľ, domény, doba platnosti, účel, `is_discovered` |
| `hcc_banners` | Konfigurácie bannera: región, `is_default`, JSON config, `version` |
| `hcc_consent_logs` | Záznamy súhlasu: uuid, verzia bannera, kategórie, akcia, región, `ip_hash`, `user_agent_hash` |

Nastavenia idú do `wp_options` s prefixom `hcc_` a sú validované cez schému
v `Options::get_schema()`.

## REST API

Namespace `hcc/v1`. **Všetky** endpointy majú `permission_callback` —
`__return_true` nie je nikde. Čítanie ani zápis nie sú prístupné pre
neprihláseného návštevníka.

| Endpoint | Metódy | Popis |
| --- | --- | --- |
| `/dashboard` | `GET` | Súhrn pre dashboard |
| `/categories` | `GET`, `POST` | Zoznam / vytvorenie |
| `/categories/<id>` | `GET`, `PUT`, `DELETE` | Detail / aktualizácia / zmazanie |
| `/cookies` | `GET`, `POST` | Zoznam s filtrami / vytvorenie |
| `/cookies/<id>` | `GET`, `PUT`, `DELETE` | Detail / aktualizácia / zmazanie |
| `/cookies/bulk-category` | `POST` | Hromadné priradenie ku kategórii |
| `/banners` | `GET`, `POST` | Zoznam / vytvorenie |
| `/banners/<id>` | `GET`, `PUT`, `DELETE` | Detail / aktualizácia / zmazanie |
| `/banner-defaults` | `GET` | Predvolená konfigurácia + zoznam layoutov |
| `/consent-logs` | `GET` | Log súhlasov, filtre, súhrn |
| `/settings` | `GET`, `POST` | Všetky / viac naraz |
| `/settings/<id>` | `GET`, `PUT`, `DELETE` | Detail / zmena / reset |
| `/consent` | `GET`, `POST` | Stav súhlasu / uloženie (bez capability) |
| `/consent/revoke` | `POST` | Odvolanie súhlasu (bez capability) |

Argumenty sú typované cez `args` (napr. `per_page` je `integer` s defaultom 20),
takže REST ich validuje predtým, než sa dostanú do repository.

Consent endpointy sú jediné s `__return_true` — musia byť dostupné
návštevníkovi, ktorý ešte nie je prihlásený. Zápis chráni `X-WP-Nonce` a
rate limit.

Export CSV ide cez `admin-post.php?action=hcc_export_consent_logs` s nonce,
nie REST — prehliadač musí dostať súbor, nie JSON.

## Admin obrazovky

Plugin má dve vrstvy adminu:

1. **React SPA** v `admin/ui/dist` — ak existuje `index.asset.php`, `Admin_Menu`
   načíta build a shell dostane bootstrap dáta cez `data-hcc-bootstrap`
   (REST URL, nonce, počiatočná trasa, prekladové reťazce).
2. **PHP fallback** — `Fallback_Pages` vykreslí prehľad, katalóg cookies,
   kategórie, banner, logy a nastavenia. Kým React nie je zbuildovaný,
   plugin je plne spravovateľný. V zálohe je aj admin notice s príkazom
   `npm run build`.

`@wordpress/scripts` generuje `index.asset.php` so zoznamom závislostí —
`Admin_Menu` ho číta, aby zoznam neduplikoval.

### Prečo je `admin/ui/dist` v gite

Build je commitnutý zámerne. Plugin sa inštaluje cez git alebo FTP a bez
buildu by React admin nefungoval — používateľ by musel na serveri spustiť
Node.js, čo na bežnom hostingu nie je možné. PHP fallback obrazovky slúžia
ako rezerva, nie ako plán.

Pri zmene React kódu treba po `npm run build` commitnúť aj `admin/ui/dist`.

Náhľad bannera používa **ten istý renderer** ako frontend, takže ukazuje to,
čo návštevník naozaj uvidí. V admin CSS je banner `position: static`, inak by
ako `position: fixed` prekryl celú administráciu.

## Consent banner

### Prečo server-side render

Banner sa kreslí na serveri (`wp_footer`, priorita 20) a JS ho iba
hydrátuje. Ak by sa vykresloval až cez JS, návštevník by najprv videl stránku
bez bannera a banner by potom „skočil" do obrazovky — presne to je FOUC, na ktorý
sa sťažovalo na CookieYes.

Štýly sú splitované na dve časti:

* `banner-critical.css` — pár desiatok riadkov vložených inline do `<head>`
  s `wp_head: 1`, aby bol banner štýlovaný už pri prvom paint-e
* `banner.css` — zvyšok, načítaný normálne

`consent-banner.js` sa načíta v päte (`$in_footer = true`) a DOM nikdy
neprestavuje — len pripája obsluhy udalostí.

### Layouty

| Layout | Tvar | Kedy |
| --- | --- | --- |
| `default` | Obsah vľavo, tlačidlá vpravo, kategórie sa roztvoria pod nimi | Predvolený |
| `bar` | Všetko v jednom riadku, kategórie pod nadpisom | Úzky banner, mobil |
| `box` | Karta uprostred obrazovky | Maximálna pozornosť |

Pozícia (`top` / `bottom`) a modálny režim sú nezávislé od layoutu.

### Rovnocenné tlačidlá

`Prijať všetko` a `Odmietnuť` majú rovnakú veľkosť, hrúbku a farebnú váhu.
`Odmietnuť` nie je sivé ani menšie. Vyžaduje to usmernenie EDPB — banner, v ktorom
je odmietnutie opticky menej výhodné, je dark pattern.

Checkboxy sú vždy predznačkované pre *neodmietnuté* kategórie, nie
predznačkované všetky. Kategória `necessary` je zapnutá a `disabled` — bez nej
stránka nemá fungovať.

### Verzionovanie bannera

`version` sa inkrementuje pri každej zmene konfigurácie. Súhlas v cookie nesie
verziu pri uložení. Ak používateľ zmení texty bannera, starý súhlas prestane
platiť a banner sa zobrazí znova — inak by návštevník súhlasil s niečím iným,
než čo vidí.

### Regionálne bannery

`Banner_Repository::for_region()` hľadá banner presne pre región, inak použije
predvolený. Je možné mať iný text pre EÚ a iný pre USA — napr. v USA CCPA
umožňuje opt-out, takže formulácia „ súhlas musíte udeliť" by bola zavádzajúca.

### Shortcodes

```
[hcc_revoke_consent label="Nastavenia cookies"]
[hcc_cookie_policy_link]
```

Prvý vykreslí plávajúce tlačidlo na odvolanie súhlasu. GDPR vyžaduje, aby cesta
odvolania bola rovnako jednoduchá ako udelenie súhlasu — inak je súhlas
neplatný.

Druhý vypíše odkaz na stránku s cookie policy. Ak v nastaveniach nie je
zvolená stránka, shortcode vráti prázdny string — radšej žiadny odkaz než
odkaz na 404.

### Verejné API

```js
window.hccBanner.show();          // zobraziť banner (revoke)
window.hccBanner.hide();
window.hccBanner.getSelection();  // ['necessary', 'statistics']
window.hccBanner.openDetails();   // rozbaliť kategórie
```

## Blokovanie skriptov

### Ako to funguje

`blocker.js` sa vypíše do `<head>` s prioritou `wp_head: 0` — teda pred
akýmkoľvek skriptom tretej strany. Konfigurácia (`window.hccBlockerConfig`)
mu príde ako inline skript ešte pred ním.

Súběžne bežia tri režimy:

1. **`document.createElement` je prekrytý.** Zachytí skripty, ktoré si vytvárajú
   pluginy počas behu — chat widgety, analytics, A/B testy. Zachytáva
   `node.src = …` aj `node.setAttribute('src', …)`.
2. **`MutationObserver`** na `document.documentElement` s `subtree: true`.
   Zachytí skripty vložené cez `innerHTML` alebo priamo serverovým výstupom.
3. **Prehľad existujúcich uzlov** pri `DOMContentLoaded` na `script[src]` aj
   `iframe[src]`.

Žiadne manuálne tagovanie `data-category` na skriptoch a žiadne integračné
adaptery pre konkrétne pluginy — to je hlavný rozdiel oproti Complianz.

### Ako sa blokuje

`<script>` dostane `type="text/plain"` (pôvodný `type` sa uloží do
`data-hcc-original-type`). `<iframe>` dostane odstránený `src` (pôvodný v
`data-hcc-original-src`). Uzol zostáva v DOM, takže ho vieme nahradiť bez toho,
aby sme museli zisťovať jeho pozíciu nanovo.

Kategórie `necessary` sa neblokujú nikdy, ani bez súhlasu.

### Odblokovanie

Po súhlase sa uzol **nahradí novým**, nie iba vráti pôvodný `type`. Prehliadače
s už načítaným prvkom v cache by obnovenie `type` nenačítalo znova. Pôvodné
atribúty sa prenášajú okrem `data-hcc*` a nášho `type`/`src`.

Vyvolá sa event `hcc:category-enabled` s `{ category, restored }`.

### Verejné API

```js
window.hccConsent.hasConsent( 'marketing' );  // boolean
window.hccConsent.getGranted();               // ['necessary', 'statistics']
window.hccConsent.getBlocked();               // [{ src, category }]
window.hccConsent.save( [ 'necessary', 'marketing' ] );
window.hccConsent.revoke();
```

`save()` pošle súhlas na REST, odblokuje uzly a vyvolá eventy. `revoke()`
odvolá súhlas — plugin prepíše banner, aby návštevník ho videl znova.

### Events

| Event | Kedy |
| --- | --- |
| `hcc:ready` | Bloker je aktívny |
| `hcc:before-block` | Skript sa práve zablokoval |
| `hcc:after-unblock` | Skript sa práve odblokoval |
| `hcc:category-enabled` | `{ category, restored }` po odblokovaní kategórie |

```js
document.addEventListener( 'hcc:category-enabled', function ( e ) {
	if ( e.detail.category === 'marketing' && window.et_pb_map_init ) {
		jQuery( '.et_pb_map_container' ).each( function ( i, el ) {
			window.et_pb_map_init( jQuery( el ) );
		} );
	}
} );
```

Posledný príklad je dôvod, prečo event existuje: Divi mapy sa inicializujú pri
prvom DOM loade, keď kontajner ešte nebol vložený. Po odblokovaní treba
inicializáciu zopakovať.

## Súhlas a jeho ukladanie

### Cookie `hcc_consent`

Payload je base64(JSON) — čitateľný v JS bez dešifrovania, zároveň neprezrádza
nič, čo by sa dalo zneužiť. `HttpOnly = false`, pretože frontend JS musí vedieť
stav súhlasu ešte pred načítaním REST.

```json
{
  "uuid": "…",
  "categories": ["necessary", "statistics"],
  "given": true,
  "t": 1767225600,
  "bv": 1
}
```

Dôležité pravidlá:

* `necessary` je vždy prítomná a vždy súhlasná — bez nej by stránka nemala
  fungovať. Nie je to voľba návštevníka.
* Bez uloženého súhlasu je súhlasná **iba** `necessary`. Súhlas musí byť
  udelený pred spracovaním, nie potom.
* Ak používateľ zmení texty bannera, `version` (`bv`) sa inkrementuje a starý
  súhlas prestane platiť. Bez toho by návštevník súhlasil s niečím iným,
  než čo vidí.

### Tabuľka `hcc_consent_logs`

Dôkaz o súhlase pre GDPR. Prístupný cez REST na admine, na frontende nie.

* IP adresa sa **nikdy** neukladá v čitateľnej forme. Ukladá sa iba
  SHA-256 hash so `wp_salt('nonce')` — z neho sa nedá IP získať späť,
  ale dá sa overiť, že ide o toho istého návštevníka.
* User agent sa tiež ukladá iba ako hash.
* Akcie sú `accept_all`, `reject_all`, `custom`.
* Retention defaultne 365 dní, denné čistenie cez `wp_schedule_event()`.
  Konfigurátelné cez `hcc_log_retention_days` (0 = bez mazania).
* Tabuľky prežijú deaktiváciu pluginu. Pri odinštalácii sa mažú **len** vtedy,
  keď je v nastaveniach zapnuté `delete_data_on_uninstall`.

### Odvolanie súhlasu

`POST /hcc/v1/consent/revoke` zmaže cookie a zapíše záznam s `given = false`.
GDPR vyžaduje, aby cesta odvolania bola rovnako jednoduchá ako udelenie
súhlasu.

### Rate limit

`POST /hcc/v1/consent` je obmedzený na 10 požiadaviek za minútu na IP
(konfigurátelné cez `rate_limit_per_min`). Bez limitu by bot mohol zapĺňať
tabuľku logov. Identifikátorom je hash IP, nie IP samotná.

## REST API (consent)

| Endpoint | Metóda | Popis |
| --- | --- | --- |
| `/hcc/v1/consent` | `GET` | Aktuálny stav súhlasu, región, zákon |
| `/hcc/v1/consent` | `POST` | Uloží súhlas |
| `/hcc/v1/consent/revoke` | `POST` | Odvolá súhlas |

POST endpointy vyžadujú `X-WP-Nonce`. Akcia musí sedieť s obsahom:
`accept_all` vždy uloží všetky kategórie, `reject_all` vždy iba `necessary`.
Bez toho by log klamal o tom, čo návštevník urobil.

Crud endpointy sú v tabuľke vyššie.

## Súkromie a GDPR

* Tlačidlá "Prijať všetko" a "Odmietnuť" majú rovnakú vizuálnu váhu
  (požiadavka EDPB).
* Žiadne predznačkované políčka, žiadne skryté tlačidlá.
* Plugin nikdy nevolá externé API na detekciu regiónu — predvolene sa
  spolieha len na `Accept-Language`. GeoIP je voliteľný cez filter
  `hcc_geoip_country`.

## Vývoj

```bash
composer install          # dev závislosti (phpcs, phpunit, WPCS)
composer lint             # phpcs — WordPress Coding Standards
composer lint:fix         # phpcbf — automatická oprava
composer test             # PHPUnit — unit testy bez databázy

npm install
npm run build             # admin/ui/src → admin/ui/dist (commitnúť!)
npm run start             # dev build s watch
npm run lint:js           # ESLint
npm run lint:css          # Stylelint
npm run format            # Prettier

npm install --no-save jsdom
npm run test:js           # testy blockera a bannera cez jsdom
```

### Ako `wp-scripts` voláme

`@wordpress/scripts` hľadá vstup v `src/` a výstup píše do `build/`. Náš
zdrojový kód je v `admin/ui/src`, preto používame CLI prepínače:

```bash
wp-scripts build --webpack-src-dir=admin/ui/src --output-path=admin/ui/dist
```

Vlastný `webpack.config.js` by prepísal celé `module.rules` a zrušil
`babel-loader` — JSX by sa neparsoval. Predvolené nastavenia
`@wordpress/scripts` sú dostatočné.

### ESLint

`.eslintrc.json` je vlastný, nie `plugin:@wordpress/recommended`. Ten ťahá
`@typescript-eslint`, ktorý sa s Node 24 láme
(`Cannot read properties of undefined (reading 'Intrinsic')`). TypeScript
nepoužívame, takže vlastná konfigurácia je aj rýchlejšia.

`react/prop-types` je vypnuté — typy sú v JSDoc komentároch, nie v
`propTypes`. `no-var` je vypnuté pre `blocker.js` a `consent-banner.js`:
tie bežia ako IIFE v `<head>` pred všetkým, kde by `let`/`const` mohli
naraziť na TDZ.

GitHub Actions (`.github/workflows/php.yml`) pri každom pushu na `main` spúšťa:

* `php -l` na všetkých PHP súboroch (PHP 8.1 aj 8.3)
* PHPCS — WordPress Coding Standards + PHPCompatibility
* `composer validate --strict`
* PHPUnit — unit testy
* `node --check` a jsdom testy pre `blocker.js`

### Testovanie bez WordPressu

`tests/stubs.php` definuje minimálne stuby WordPress funkcií
(`get_transient()`, `apply_filters()`, `sanitize_key()`…), takže čisté triedy
sa dajú testovať bez databázy a bez WP inštalácie:

```bash
vendor/bin/phpunit              # všetky testy
vendor/bin/phpunit --testdox   # s popiskami testov
```

Kryté sú `Script_Catalog` (normalizácia, zhoda patternov, filtery),
`Consent_Cookie` (validácia payloadu, poškodená cookie, verzie bannera),
`Region_Resolver` (priorita zdrojov, režimy, fallback),
`Blocker_Config` (konfigurácia pre JS),
`Banner_Repository` (sanitizácia farieb a textov, verzionovanie) a
`hcc_get_regions()` (mapovanie krajín, typy súhlasu).

### Testovanie JavaScriptu

`blocker.js` sa testuje cez **jsdom** — reálne DOM API bez prehliadača:

```bash
npm install --no-save jsdom
node tests/js/blocker.test.mjs
```

Testy `blocker.js` (17) pokrývajú blokovanie cez všetky tri režimy
(serverový výstup, `innerHTML`, `createElement`), iframe blokovanie,
longest-match, odblokovanie so zachovaním pôvodného `type`, custom eventy
a verejné API.

Testy `consent-banner.js` (18) načítajú **serverom vykreslený HTML** — presne
tak, ako by sa banner objavil vo WordPresse. Kontrolujú, že `necessary` je
zapnutý a nedá sa vyradiť, že `accept_all` odfajkuje všetko, že nonce cestuje
v hlavičke, že blocker dostane nové kategórie, a že prázdny banner nespôsobí
chybu.

### Testovanie v reálnom prehliadači

```js
window.hccConsent.getBlocked();
// [{ src: 'https://www.googletagmanager.com/gtag/js', category: 'marketing' }]
```

V DevTools v záložke Network filter `hcc` a `scripts/…` — blokované skripty
sa nedostanú do siete. Filter `hcc_blocker_debug` na true vypíše každé
blokovanie do konzoly.

## Licencia

GPL-3.0-or-later. Plný text je v [LICENSE](LICENSE).