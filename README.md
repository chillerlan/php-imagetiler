# php-imagetiler

A script for PHP 7.2+ to cut images (maps) into pieces (tiles). Based on the [map tiler script by Fedik](https://github.com/Fedik/php-maptiler).
This script will keep the proportions of the input image and generate only necessary tiles - no need for square input files!

[![Packagist version][packagist-badge]][packagist]
[![License][license-badge]][license]
[![Travis CI][travis-badge]][travis]
[![CodeCov][coverage-badge]][coverage]
[![Scrunitizer CI][scrutinizer-badge]][scrutinizer]
[![Packagist downloads][downloads-badge]][downloads]
[![PayPal donate][donate-badge]][donate]

[packagist-badge]: https://img.shields.io/packagist/v/chillerlan/php-imagetiler.svg?style=flat-square
[packagist]: https://packagist.org/packages/chillerlan/php-imagetiler
[license-badge]: https://img.shields.io/github/license/chillerlan/php-imagetiler.svg?style=flat-square
[license]: https://github.com/chillerlan/php-imagetiler/blob/master/LICENSE
[travis-badge]: https://img.shields.io/travis/chillerlan/php-imagetiler.svg?style=flat-square
[travis]: https://travis-ci.org/chillerlan/php-imagetiler
[coverage-badge]: https://img.shields.io/codecov/c/github/chillerlan/php-imagetiler.svg?style=flat-square
[coverage]: https://codecov.io/github/chillerlan/php-imagetiler
[scrutinizer-badge]: https://img.shields.io/scrutinizer/g/chillerlan/php-imagetiler.svg?style=flat-square
[scrutinizer]: https://scrutinizer-ci.com/g/chillerlan/php-imagetiler
[gemnasium-badge]: https://img.shields.io/gemnasium/chillerlan/php-imagetiler.svg?style=flat-square
[gemnasium]: https://gemnasium.com/github.com/chillerlan/php-imagetiler
[downloads-badge]: https://img.shields.io/packagist/dt/chillerlan/php-imagetiler.svg?style=flat-square
[downloads]: https://packagist.org/packages/chillerlan/php-imagetiler/stats
[donate-badge]: https://img.shields.io/badge/donate-paypal-ff33aa.svg?style=flat-square
[donate]: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=WLYUNAT9ZTJZ4

# Documentation

## Requirements
- PHP 7.2+
- the [ImageMagick extension](https://www.imagemagick.org)
- a crapload of RAM, CPU power and free disk space
- image optimization utilities (optional), see [psliwa/image-optimizer](https://github.com/psliwa/image-optimizer#supported-optimizers)

## Installation
**requires [composer](https://getcomposer.org)**

*composer.json* (note: replace `dev-master` with a version boundary)
```json
{
	"require": {
		"php": ">=7.2.0",
		"ext-imagick": "*",
		"chillerlan/php-imagetiler": "dev-master"
	}
}
```

### Manual installation
Download the desired version of the package from [master](https://github.com/chillerlan/php-imagetiler/archive/master.zip) or
[release](https://github.com/chillerlan/php-imagetiler/releases) and extract the contents to your project folder.  After that:
- run `composer install` to install the required dependencies and generate `/vendor/autoload.php`.
- if you use a custom autoloader, point the namespace `chillerlan\Imagetiler` to the folder `src` of the package

Profit!

## Usage
Use the [example](https://github.com/chillerlan/php-imagetiler/blob/master/examples/imagetiler.php) for live testing.
```php
// invoke an options instance
$options = new ImagetilerOptions([
	'zoom_min'             => 0,
	'zoom_max'             => 8,
	'zoom_normalize'       => 6,
	'fill_color'           => 'transparent',
	'fast_resize'          => true,
	// ... whatever you need
]);

// invoke and run the tiler
$tiler  = new Imagetiler($options);
$tiler->process('/path/to/image.png', '/path/to/output/');
```

That's it!

## API

### `Imagetiler` public methods
method | return | description
------ | ------ | -----------
`__construct(ContainerInterface $options = null, LoggerInterface $logger = null)` | - | see [`ContainerInterface`](https://github.com/chillerlan/php-traits/blob/master/src/ContainerInterface.php) and [`LoggerInterface`](https://github.com/php-fig/log). Invokes an empty `ImagetilerOptions` object and a `Psr\NullLogger` if the respective parameters aren't set.
`setOptions(ContainerInterface $options)` | `Imagetiler` | set options on-the-fly, called internally by the constructor
`process(string $image_path, string $out_path)` | `Imagetiler` | processes the given image from `$image_path` and dumps the output to `$out_path`

### `ImagetilerOptions` properties
property | type | default | allowed | description
-------- | ---- | ------- | ------- | -----------
`$tile_size` | int | 256 | positive int | width/height of a single tile
`$zoom_min` | int | 0 | positive int | minimum zoom level
`$zoom_max` | int | 8 | positive int | maximum zoom level
`$zoom_normalize` | int | null | positive int | this zoom level represents the size of the original image. zoom levels higher than this will be upscaled, which may take some time and resources depending on the size of the input image.
`$tms` | bool | false | * | if set to true - the origin will be set to bottom left, +y upwards, according to [Tile Map Service Specification](http://wiki.osgeo.org/wiki/Tile_Map_Service_Specification#TileMap_Diagram), otherwise the origin is on the top left, +y downwards, like described by the [Google Maps specification](https://developers.google.com/maps/documentation/javascript/coordinates#tile-coordinates)
`$fill_color` | string | '#000000' | * | the fill color for leftover space, can be transparent for png
`$memory_limit` | string | '-1' | * | see [php.ini settings](https://secure.php.net/manual/ini.core.php#ini.memory-limit)
`$store_structure` | string | '%1$d/%2$d/%3$d' | * | storage structure - can be anything. %1$d = zoom, %2$d = x, %3$d = y. see [sprintf()](https://secure.php.net/manual/function.sprintf.php)
`$fast_resize` | bool | false | * | determines whether to use fast `Imagick::scaleImage()` (true) or slow `Imagick::resizeImage()` (false)
`$resize_filter` | int | `Imagick::FILTER_ROBIDOUXSHARP` | `Imagick::FILTER_*` | see `Imagick::resizeImage()` and [Imagick filter constants](https://secure.php.net/manual/imagick.constants.php)
`$resize_blur` | float | 1.0 | positive float | see `Imagick::resizeImage()`
`$tile_format` | string | 'png' | png, jpg | see [Imagick formats](http://www.imagemagick.org/script/formats.php)
`$tile_ext` | string | null | * | tile image extension - autodetected from format if none given.
`$quality_jpeg` | int | 80 | 0-100 | quality of the saved image in jpeg format
`$imagick_tmp` | string | null | * | ImageMagick tmp folder
`$overwrite_base_image` | bool | false | * | 
`$overwrite_tile_image` | bool | false | * | 
`$clean_up` | bool | true | * | whether or not to delete temp images
`$optimize_output` | bool | false | * | enable image optimization
`$optimizer_settings` | array | [] | * | image optimizer settings, see [ImageOptimizer configuration](https://github.com/psliwa/image-optimizer#configuration)
