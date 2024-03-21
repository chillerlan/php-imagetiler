# php-imagetiler

A script for PHP 7.4+ to cut images (maps) into pieces (tiles). Based on the [map tiler script by Fedik](https://github.com/Fedik/php-maptiler).
This script will keep the proportions of the input image and generate only necessary tiles - no need for square input files!

[![PHP Version Support][php-badge]][php]
[![Packagist version][packagist-badge]][packagist]
[![License][license-badge]][license]
[![Continuous Integration][gh-action-badge]][gh-action]
[![CodeCov][coverage-badge]][coverage]
[![Packagist downloads][downloads-badge]][downloads]

[php-badge]: https://img.shields.io/packagist/php-v/chillerlan/php-imagetiler?logo=php&color=8892BF&logoColor=fff
[php]: https://www.php.net/supported-versions.php
[packagist-badge]: https://img.shields.io/packagist/v/chillerlan/php-imagetiler.svg?logo=packagist&logoColor=fff
[packagist]: https://packagist.org/packages/chillerlan/php-imagetiler
[license-badge]: https://img.shields.io/github/license/chillerlan/php-imagetiler.svg
[license]: https://github.com/chillerlan/php-imagetiler/blob/main/LICENSE
[gh-action-badge]: https://img.shields.io/github/actions/workflow/status/chillerlan/php-imagetiler/ci.yml?branch=main&logo=github&logoColor=fff
[gh-action]: https://github.com/chillerlan/php-imagetiler/actions?query=workflow%3A%22Continuous+Integration%22
[coverage-badge]: https://img.shields.io/codecov/c/github/chillerlan/php-imagetiler.svg?logo=codecov&logoColor=fff
[coverage]: https://codecov.io/github/chillerlan/php-imagetiler
[downloads-badge]: https://img.shields.io/packagist/dt/chillerlan/php-imagetiler.svg?logo=packagist&logoColor=fff
[downloads]: https://packagist.org/packages/chillerlan/php-imagetiler/stats

# Documentation

## Requirements
- PHP 7.4+
- the [ImageMagick extension](https://www.imagemagick.org)
- a crapload of RAM, CPU power and free disk space
- image optimization utilities (optional), see [psliwa/image-optimizer](https://github.com/psliwa/image-optimizer#supported-optimizers)

## Installation
**requires [composer](https://getcomposer.org)**

*composer.json* (note: replace `dev-main` with a version boundary)
```json
{
	"require": {
		"php": "^7.4 || ^8.0",
		"ext-imagick": "*",
		"chillerlan/php-imagetiler": "dev-main"
	}
}
```

Profit!

## Usage
Use the [example](https://github.com/chillerlan/php-imagetiler/blob/main/examples/imagetiler.php) for live testing.
```php
// invoke an options instance
$options = new ImagetilerOptions([
	'zoom_min'             => 0,
	'zoom_max'             => 8,
	'zoom_normalize'       => 6,
	'fill_color'           => 'transparent',
	'fast_resize'          => true,
	'optimize_output'      => true,
	// ... whatever you need
]);

// see https://github.com/psliwa/image-optimizer#configuration
$optimizer = (new OptimizerFactory([]))->get();

// invoke and run the tiler
$tiler  = new Imagetiler($options, $optimizer);
$tiler->process('/path/to/image.png', '/path/to/output/');
```

That's it!

### Memory trouble
If you're running into issues with ImageMagick complaining about not enough space on the cache path, you might want to check the [`policy.xml`](https://github.com/ImageMagick/ImageMagick/blob/main/config/policy.xml) in the ImageMagick installation path (on Windows).
For your consideration: an image of 49152x49152 will generate a cache file of ~28.5GB, 
### Image optimizers

- PNG
  - [advpng](https://github.com/amadvance/advancecomp)
  - [optipng](http://optipng.sourceforge.net/)
  - [pngcrush](https://pmt.sourceforge.io/pngcrush/)
  - [pngquant](https://pngquant.org/)
- JPG
  - [jpegoptim](https://github.com/XhmikosR/jpegoptim-windows)
  - [jpegtran](https://jpegclub.org/jpegtran/)

## API

### `Imagetiler` public methods
method | return | description
------ | ------ | -----------
`__construct(ContainerInterface $options = null, LoggerInterface $logger = null)` | - | see [`SettingsContainerInterface`](https://github.com/chillerlan/php-settings-container/blob/main/src/SettingsContainerInterface.php) and [`LoggerInterface`](https://github.com/php-fig/log). Invokes an empty `ImagetilerOptions` object and a `Psr\NullLogger` if the respective parameters aren't set.
`setOptions(ContainerInterface $options)` | `Imagetiler` | set options on-the-fly, called internally by the constructor
`setOptimizer(Optimizer $optimizer)` | `Imagetiler` | set an optimizer instance on-the-fly, called internally by the constructor
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
`$fast_resize_upsample` | bool | false | * | determines whether to use fast `Imagick::scaleImage()` (true) or slow `Imagick::resizeImage()` (false)
`$resize_filter_upsample` | int | `Imagick::FILTER_ROBIDOUXSHARP` | `Imagick::FILTER_*` | see `Imagick::resizeImage()` and [Imagick filter constants](https://secure.php.net/manual/imagick.constants.php)
`$resize_blur_upsample` | float | 1.0 | positive float | see `Imagick::resizeImage()`
`$fast_resize_downsample` | bool | false | * | see `$fast_resize_upsample`
`$resize_filter_downsample` | int | `Imagick::FILTER_LANCZOSRADIUS` | `Imagick::FILTER_*` | see `$resize_filter_upsample`
`$resize_blur_downsample` | float | 1.0 | positive float | see `$resize_blur_upsample`
`$tile_format` | string | 'png' | png, jpg | see [Imagick formats](http://www.imagemagick.org/script/formats.php)
`$tile_ext` | string | null | * | tile image extension - autodetected from format if none given.
`$quality_jpeg` | int | 80 | 0-100 | quality of the saved image in jpeg format
`$imagick_tmp` | string | null | * | ImageMagick tmp folder
`$overwrite_base_image` | bool | false | * |
`$overwrite_tile_image` | bool | false | * |
`$clean_up` | bool | true | * | whether or not to delete temp images
`$optimize_output` | bool | false | * | enable image optimization (requires `Optimizer` instance)
`$no_temp_baseimages` | bool | false | * | whether or not to create and save temporary base images (may save resources)
