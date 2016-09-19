yql-woeidfinder
===============
Reverse Geocoding using YQL

## Installation

Just add `"famex/yql-woeidfinder": "0.5.*",` to your `composer.json` file.

## Usage

```php
$yqlWoeidFinder = new \Famex\YqlWoeidFinder\YqlWoeidFinder();
$place = $yqlWoeidFinder->getPlace(-33.856469, 151.215413);
```