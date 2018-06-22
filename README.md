# ePub lib for PHP

[![Build Status](https://travis-ci.org/ridi/epub.svg?branch=master)](https://travis-ci.org/ridi/epub)

## Installation

### Composer (preferred)

Add `ridibooks/epub` to your `composer.json` file.

```javascript
{
    "require": {
        "ridibooks/epub": "dev-master"
    }
}
```

Then just run the `composer.phar install` (or `composer.phar update` if
you added this to an existing `composer.json` file).

```bash
wget http://getcomposer.org/composer.phar
php composer.phar install
```

### Symfony2 Deps

Add the following to your `deps` file

```ini
[epub]
    git=http://github.com/ridi/epub.git
```

After you have run the `bin/vendors install` script, add the following
to your `autoload.php` file.

```php
<?php

$loader->registerNamespaces(array(
    // ... other namespaces ...
    'ePub'  =>  __DIR__.'/../vendor/epub/src'
));
```

## Usage

```php
<?php

$reader = new \ePub\Reader();
$epub = $reader->load('my-book.epub');

printf("Title: %s\n", $epub->getMetadata()->get('title'));
```


## Resources

 * http://www.hxa.name/articles/content/epub-guide_hxa7241_2007.html
