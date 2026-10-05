# WordPress Coding Standards

```bash
composer require --dev wp-coding-standards/wpcs phpcompatibility/phpcompatibility-wp dealerdirect/phpcodesniffer-composer-installer
phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs,vendor/phpcompatibility/php-compatibility,vendor/phpcompatibility/phpcompatibility-wp
phpcs
```

WordPress potrebuje ešte `phpcs.xml` (aktuálne `phpcs.xml.dist`) a core sniffs
pre REST API a databázu:

```bash
composer require --dev wp-coding-standards/wpcs
composer require --dev phpcompatibility/phpcompatibility-wp
```