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
  Capabilities.php                 Capability manage_hybrid_cookies
  Activator.php                    dbDelta, seedovanie
  Deactivator.php                  Čistenie transientov
  I18n.php                         Načítanie prekladov, EN + SK
  autoload-fallback.php            PSR-4 fallback bez Composera
  helpers/autoload.php             Bootstrap pre aktiváciu/deaktiváciu

admin/
  Admin_Menu.php                   wp-admin menu + shell pre React

modules/
  Categories/                      Kategórie súhlasu
  Cookies/                         Katalóg cookies
  Blocker/                         Blokovanie skriptov
  Consent/                         Súhlas a logy
  Banner/                          Consent banner

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
| `hcc_consent_recorded` | Návštevník udelil súhlas (payload: uuid, kategórie, akcia) |
| `hcc_before_block` | Skript sa blokuje |
| `hcc_after_unblock` | Skript sa odblokoval |
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

## Súkromie a GDPR

* IP adresa sa **nikdy** neukladá v čitateľnej forme — iba SHA-256 hash
  s `wp_salt()`.
* User agent sa tiež ukladá iba ako hash.
* Tabuľky prežijú deaktiváciu pluginu. Pri odinštalácii sa mažú **len** vtedy,
  keď je v nastaveniach zapnuté `delete_data_on_uninstall`.
* Tlačidlá "Prijať všetko" a "Odmietnuť" majú rovnakú vizuálnu váhu
  (požiadavka EDPB).

## Vývoj

```bash
composer install          # dev závislosti (phpcs, phpunit, WPCS)
composer lint             # phpcs — WordPress Coding Standards
composer lint:fix         # phpcbf — automatická oprava
composer test             # PHPUnit (vyžaduje wp-phpunit)

npm install
npm run build             # admin/ui/src → admin/ui/dist
npm run start             # dev build s watch
```

GitHub Actions (`.github/workflows/php.yml`) spúšťa `php -l`, PHPCS
a `PHPCompatibility` pre PHP 8.1 a 8.3 pri každom pushu na `main`.

## Licencia

GPL-2.0-or-later