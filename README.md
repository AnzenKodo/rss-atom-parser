# rss-atom-parser
RSS &amp; Atom feed parser for PHP. Very small and easy-to-use library for parsing your feeds.

## Getting Started
It requires PHP 5.3 or newer with CURL extension or enabled allow_url_fopen.

Install via Composer:
```bash
composer require AnzenKodo/rss-atom-parser
```

## Usage
```php
<?php
// Load plugins

require_once __DIR__.'/vendor/autoload.php';

// Array and String are supported.
$values = ["https://anzenkodo.github.io/blog/feed.xml", "https://anzenkodo.github.io/dblog/feed.xml"];
$values = "https://anzenkodo.github.io/blog/feed.xml";

$feed = RSS::feed($values);
print_r($feed);
?>
```
