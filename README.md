DCE to FCE Migration Toolchain
==============================

# Introduction

This package contains a toolchain for migration DCE to FCE in several (internal/customer) projects.
It contain's only the stuff we needed so far, so this is not a full-fledged feature enriched package,
and maybe will never become one. Feel free to fork and extend it with the things you need.

## Installation

This package is currently only available as composer package.

```shell
$ composer require sbuerk/dce-fce-migration
```

## Usage

This package comes with a centralized command to process migrations. However, migrations are project specific and thus
must be provided by the instance. They must be registered, so the command can pick them up.

### Command Usage

```shell
$ vendor/bin/typo3 dce2fce:migrate              # list migrations
$ vendor/bin/typo3 dce2fce:migrate --run        # run all registered migrations
$ vendor/bin/typo3 dce2fce:migrate --run \      # run only selected migration(s)
  --select="dce-1" --select="dce-2"             # using the select option to select by identifiers
```

Migrations are registered with the global `$GLOBALS['DCE_FCE_MIGRATIONS']`:

```php
$GLOBALS['DCE_FCE_MIGRATIONS'] = array_replace(
    $GLOBALS['DCE_FCE_MIGRATIONS'] ?? [],
    // register migrations
    \Vendor\Extension\Migrations\YourDceElementMigrationClass::class,
);
```

## TODO's

[ ] Enrich README.md with information about how to write custom DCE->FCE Migration sets
[ ] Explain basic migration sets templates
[ ] Add tests to cover the functionality

## Tagging and releasing

packagist.org is enabled via the casual github hook. TER releases are created by the "publish.yml" github workflow when tagging versions using tailor. The commit message of the tagged commit is used as TER upload comment.

> @todo TER Release not implemented yet

Example:

```shell
composer install
.Build/bin/tailor set-version 0.2.0 --no-docs
git commit -am "[RELEASE] 0.2.0 Bug fixes and improved core v11 compatibility"
git tag 0.2.0
git push
git push --tags
```
