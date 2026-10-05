# Plán vývoja — Hybrid Cookies conset R+

Rozsah: **MVP (fáza 1)** podľa špecifikácie `cookie-consent-plugin-spec.md`.
Jedna sesia = jedna časť pluginu, aby sa nepreplnilo kontextové okno.
Každá sesia končí commitom a pushom na GitHub.

## Rozhodnutia (potvrdené)

| Otázka | Rozhodnutie |
| --- | --- |
| Názov pluginu | **Hybrid Cookies conset R+** |
| Namespace PHP | `HCC\` (zrkadlí prefix) |
| REST namespace | `hcc/v1` |
| Prefix DB tabuliek | `hcc_` |
| Prefix optionov | `hcc_` |
| Text domain | `hybrid-cookies-conset-r-plus` |
| Cookie súhlasu | `hcc_consent` |
| PHP minimum | **8.1** |
| Autoload | **Composer PSR-4** |
| Admin UI | **React + `@wordpress/scripts`** |
| Rozsah | MVP fáza 1 (bez scanneru, dokumentov, cache-compat, CCPA) |
| Architektúra | Modules pattern (`Abstract_Module` + `Module_Registry`) ako v špecifikácii |
| Blokovanie | `MutationObserver` + `createElement` override (CookieYes štýl) |
| Dáta | Self-hosted, žiadny cloud, žiadne externé API |

## Konvencie projektu

- Prefix všetkých PHP tried: `HCC_`, filtriov `hcc_*`, hookov `hcc_*`.
- Slugy tried podľa WordPress štandardu: `class-hcc-foo.php`.
- Každý modul je priečinok v `modules/` s `class-<slug>-module.php`.
- Všetky DB dotazy idú cez `$wpdb` s `prepare()`, žiadne priame stringy.
- Všetky REST endpointy majú `permission_callback` (nikdy nie `__return_true` pre zápis).
- Nonce na všetkých zápisových operáciách (`wp_verify_nonce` / `X-WP-Nonce`).
- Frontend JS: vanilla, bez jQuery, `defer`, hydratácia SSR DOM (nie client-side render).
- Jazyky: en_US + sk_SK (viac jazykov nie).

---

## Sesia 1 — Infra a jadro

Cieľ: plugin sa aktivuje, vytvorí tabuľky, admin menu sa otvorí. Žiadne funkcie ešte.

- `composer.json` — PSR-4 `HCC\\` → `includes/`, `HCC\\Modules\\` → `modules/`
- Inštalácia Composera + `composer install`, `vendor/` commitnutý (aby šlo nasadiť bez Composera)
- `package.json` — `@wordpress/scripts`, `@wordpress/components`, `react-router-dom`
- Hlavný súbor `hybrid-cookies-conset-r-plus.php` — konštanty, `ABSPATH` guard, `HCC_VERSION`
- `includes/class-plugin.php` — singleton bootstrap
- `includes/abstract-module.php` — `init()`, `hooks()`, `get_slug()`
- `includes/class-module-registry.php` — discovery a načítanie modulov
- `includes/class-options.php` — typovaný wrapper nad `wp_options` + validačná schéma
- `includes/class-capabilities.php` — capability `manage_hybrid_cookies`
- `includes/class-activator.php` — `dbDelta` pre `hcc_categories`, `hcc_cookies`, `hcc_banners`, `hcc_consent_logs`
- `includes/class-deactivator.php` + `uninstall.php` — čistenie podľa nastavenia
- `includes/class-i18n.php` — načítanie `languages/`
- `admin/class-admin-menu.php` — prázdne shell menu (doplní sa v Sesii 6)
- `.gitignore`, `.gitattributes` (LF), `phpcs.xml.dist` (WPCS + PHPCompatibility 8.1)
- Odstránenie starej kostry z predchádzajúcej verzie (`includes/admin/`, `includes/api/`, `assets/`)

**Commit:** `feat: infra, PSR-4 autoload, DB schema a jadro pluginu`

---

## Sesia 2 — Konfigurácia a dátové repozitáre

Cieľ: plugin vie načítať kategórie a katalóg cookies do tabuliek.

- `config/regions.php` — krajina → región → typ súhlasu (`optin`/`optout`) → zákon, cez filter `hcc_regions`
- `config/default-categories.json` — necessary / preferences / statistics / marketing
- `modules/categories/class-categories-repository.php` — CRUD + seeding
- `modules/cookies/class-cookie-repository.php` — CRUD + `is_discovered`, `last_seen`
- `modules/blocker/class-script-catalog.php` — načítanie `known-cookies.json`, filter `hcc_script_providers`, transient 12 h
- `modules/blocker/data/known-cookies.json` — ~30 poskytovateľov (GA4, GTM, Meta, YouTube, Maps, Hotjar, TikTok, HubSpot…)
- Seedovanie predvolených kategórií pri aktivácii + admin notice keď je katalóg prázdny

**Commit:** `feat: konfiguracia regionov, kategorii a katalogu cookies`

---

## Sesia 3 — Consent

Cieľ: súhlas sa uloží do cookie a do lokálnej tabuľky, banner vie zistiť stav.

- `modules/consent/class-consent-module.php`
- `modules/consent/class-consent-cookie.php` — čítanie/zápis `hcc_consent` (JSON + banner version, 365 dní, SameSite=Lax, Secure)
- `modules/consent/class-consent-recorder.php` — zápis do `hcc_consent_logs` (uuid, salted SHA-256 IP, banner version, akcia)
- `modules/consent/class-region-resolver.php` — `Accept-Language` + manuálny prepínač v bannri, voliteľný GeoIP filter
- `REST POST /hcc/v1/consent` — nonce + rate-limit 10 req/min/IP cez transient
- Action `hcc_consent_recorded` s payloadom (hook na WooCommerce proof-of-consent v budúcnosti)
- Consent log: hash IP, žiadna čitateľná IP, žiadne UA v čitateľnej forme

**Commit:** `feat: consent cookie, lokalny log suhasov a region resolver`

---

## Sesia 4 — Blocker

Cieľ: skript tretej strany sa nenačíta bez súhlasu, aj keď ho vloží iný plugin.

- `modules/blocker/class-blocker-module.php` — enqueue, inline konfigurácia v `<head>`
- `modules/blocker/js/blocker.js` — override `document.createElement` pre `script`/`iframe`
- `MutationObserver` na `document.documentElement` (`childList`, `subtree`)
- Blokovanie: `type="text/plain"` + `data-hcc-blocked`, pôvodný uzol v záložnom zozname
- Odblokovanie po súhlase: uzol sa znovu vytvorí na pôvodnom mieste
- Custom event `hcc:category-enabled`, action hooky `hcc_before_block` / `hcc_after_unblock`
- Bezpečnostný prepínač: `document_start` blokovanie cez WP Script API (`wp_script_add_data( 'strategy' )`) kde je možné
- Consent Mode v2 default (`granted`, `denied`) — základ pre Google Consent Mode

**Commit:** `feat: auto-blocking skriptov cez MutationObserver`

---

## Sesia 5 — Banner

Cieľ: banner je vykreslený server-side (bez FOUC), hydratuje sa cez JS.

- `modules/banner/class-banner-module.php`
- `modules/banner/class-banner-repository.php` — CRUD nad `hcc_banners`, `version` inkrement pri zmene
- `modules/banner/class-banner-renderer.php` — SSR, výber bannera podľa regiónu
- `modules/banner/templates/default.php`, `bar.php`, `box.php`
- `public/class-frontend-loader.php` — enqueue, inline critical CSS v `<head>`, JS `defer`
- `public/css/banner.css` + kritické inline pravidlá
- `public/js/consent-api.js` — verejné API `window.hccConsent.*` pre vývojárov + revoke tlačidlo
- Rovnaká vizuálna váha „Prijať všetko" / „Odmietnuť" (EDPB), žiadne dark patterns, žiadne predz:checked
- Consent banner sa načíta priamo cez shortcode/block pre revoke widget

**Commit:** `feat: server-side render banneru a frontend loader`

---

## Sesia 6 — REST API a admin shell

Cieľ: kompletné REST API + funkčné PHP admin obrazovky ako fallback.

- `includes/class-rest-router.php` — registrácia namespace `hcc/v1`
- `GET/POST/PUT/DELETE /hcc/v1/cookies`, `/categories`, `/banners`, `/consent-logs`
- Capability enforcement (`manage_hybrid_cookies`), nonce, sanitizácia, validácia
- `admin/class-admin-menu.php` — Dashboard, Banner, Kategórie, Cookies, Logy, Nastavenia
- `admin/class-settings-page.php` — jednoduchý PHP fallback pre prípad, že React build chýba
- `admin/class-pagination.php` helper pre tabuľky (WP_List_Table s HPOS-safe postupom)
- Export CSV z consent logov
- `skconsent_`-ekvivalent hookov: `hcc_before_block`, `hcc_after_unblock`, `hcc_consent_recorded`, `hcc_script_providers`, `hcc_regions`, `hcc_banner_saved`

**Commit:** `feat: REST API a PHP admin obrazovky`

---

## Sesia 7 — React admin UI (shell + dashboard)

Cieľ: React SPA beží v `wp-admin`, nahradí PHP obrazovky.

- `admin/ui/` — zdrojový React kód
- `admin/ui/src/index.js`, `App.js`, `routes.js`
- `@wordpress/scripts` build → `admin/ui/dist/`
- REST klient s `wp.apiFetch` + nonce z `@wordpress/api-fetch`
- Store: cookies, categories, banners, logs, settings
- Dashboard: aktuálny stav (banner aktívny, počet cookies po kategóriách, posledné súhlasy)
- `package.json` skripty: `build`, `start`, `lint:js`

**Commit:** `feat: React admin shell a dashboard`

---

## Sesia 8 — React editor bannera s live previewom

Cieľ: vizuálny editor bannera s náhľadom bez otvorenia frontendu.

- `banner/` route: farby, texty, pozícia, layout (bar/box/default), tlačidlá
- Živý náhľad cez iframe `MessageChannel` alebo sandbox preview
- Ukladanie cez `POST /hcc/v1/banners`, inkrement `version`
- "Simple mode" (predvolené hodnoty) vs "Advanced mode" (vlastné CSS, per-región)
- `cookies/` route: zoznam s filtrovaním, CRUD, hromadná kategórizácia
- `categories/` route: CRUD, `is_necessary`, `sell_personal_data`, sort order
- Potvrdenie pri bode zmazaní, loading/error stavy

**Commit:** `feat: React editor bannera s live previewom a spravou cookies`

---

## Sesia 9 — Logy, i18n a finalizácia

Cieľ: plugin je pripravený na release.

- `logs/` route: tabuľka súhlasov, filter podľa dátumu/akcie/regiónu, export CSV, anonymizované IP
- `languages/` — `hybrid-cookies-conset-r-plus.pot`, `en_US.po/.mo`, `sk_SK.po/.mo` (EN aj SK od začiatku)
- Odomknutie všetkých stringov v React komponentoch cez `@wordpress/i18n`
- `readme.txt` (WordPress.org formát), `README.md`, `CHANGELOG.md`, `LICENSE`
- PHPStan / PHPCS (WPCS + PHPCompatibility 8.1) čistý priechod
- GitHub Actions: PHP lint, JS lint, PHPCS, smoke test matrix (WP 6.4–6.9 × PHP 8.1/8.3)
- Bezpečnostný audit: nonce, capability, sanitizácia, escapovanie na všetkých endpointoch
- GDPR checklist podľa EDPB (rovnaká váha tlačidiel, možnosť odvolať súhlas, žiadne dark patterns)

**Commit:** `feat: logy, i18n EN/SK a finalizacia pre release`

---

## Odložené (nie je v MVP)

| Položka | Fáza |
| --- | --- |
| Scanner (lokálny crawler cez wp-cron) | Beta |
| Generátor GDPR dokumentov (wizard + šablóny EU/US/generic) | Beta |
| `cache-compat` modul + adaptéry (WP Rocket, W3TC, LiteSpeed, WP Super Cache, WP Fastest Cache…) | Beta |
| Google Consent Mode v2 (plná) | Beta |
| Integrácie Divi 4/5, Hello theme, Elementor, WooCommerce, CF7, WPForms | Beta / Final |
| CCPA „Do Not Sell/Share" modul | Final |
| Viac jazykov (WPML) | mimo plánu |

## Prerepodmienky pred Sesiou 1

1. **PHP 8.1+ CLI** na tomto stroji (momentálne `php` nie je v PATH) — na lint a na Composer.
2. **Composer** — `composer install` pred prvým pushom, aby `vendor/` bol commitnutý.
3. **GitHub repozitár** `stemja1/Hybrid-Cookies-conset-R` + SSH kľúč alebo PAT (momentálne `ssh -T git@github.com` = *Permission denied (publickey)*, `gh` CLI nie je nainštalované).

Bez prvých dvoch bodov sa kód dá písať, ale nedá sa spustiť lint ani `composer install`. Tretí bod je potrebný až pri pushi.