Composer Frontline
==================

[![Downloads this Month](https://img.shields.io/packagist/dm/dg/composer-frontline.svg)](https://packagist.org/packages/dg/composer-frontline)

**Updates all the version constraints of dependencies in the composer.json file to their latest version.**

How do you update outdated dependencies?
----------------------------------------

When you install a package using `composer require vendor/package`, a entry for example `"vendor/package": "^1.4"` is added to the composer.json file.
It means that Composer can update to patch and minor releases: 1.4.1, 1.5.0 and so on.
But not to major version, which means, in this example, 2.0 and higher.

To discover new releases of the packages, you run `composer outdated`. Some of those updates can be major releases.
Running `composer update` won’t update the version of those.

To update to a new major versions, use this tool Composer Frontline.

Usage
-----

Install it:

```shell
composer require dg/composer-frontline --dev
```

then run it:

```shell
composer frontline
```

it will print something like:

```
vendor/package      ^1.4  →  ^2.0
nette/mail          ^1.0  →  ^3.1
latte/latte         ^1.6  →  ^2.8
```

This will upgrade all the version hints in the composer.json file, in `require` and `require-dev` sections. It only modifies composer.json file.
So run `composer update` to update your packages.

You can also update only specific packages using names and wildcards:

```shell
composer frontline  nette/*
composer frontline  doctrine/* symfony/console
```

Make sure your composer.json file is in version control and all changes have been committed. This will overwrite your composer.json file.
