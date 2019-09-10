<?php
/**
 * Trait ImagetilerOptionsTrait
 *
 * @filesource   ImagetilerOptionsTrait.php
 * @created      20.06.2018
 * @package      chillerlan\Imagetiler
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 */

namespace chillerlan\Imagetiler;

use Imagick;

use function in_array, max, strtolower;

trait ImagetilerOptionsTrait{

	/**
	 * width/height of a single tile
	 *
	 * @var int
	 */
	protected $tile_size = 256;

	/**
	 * minimum zoom level
	 *
	 * @var int
	 */

	protected $zoom_min = 0;

	/**
	 * maximum zoom level
	 *
	 * @var int
	 */
	protected $zoom_max = 8;

	/**
	 * normalize zoom level
	 *
	 * this zoom level represents the zoom/size of the original image.
	 * zoom levels higher than this will be upscaled up to $zoom_max,
	 * lower will be downsampled to $zoom_min, which may take
	 * some time and resources depending on the size of the input image.
	 *
	 * Defaults to $zoom_max
	 *
	 * @var int
	 */
	protected $zoom_normalize = null;

	/**
	 * if set to true - the origin will be set to bottom left, +y upwards
	 *
	 * @see http://wiki.osgeo.org/wiki/Tile_Map_Service_Specification#TileMap_Diagram
	 *
	 * otherwise the origin is on the top left, +y downwards (default)
	 * @see https://developers.google.com/maps/documentation/javascript/coordinates#tile-coordinates
	 *
	 * @var bool
	 */
	protected $tms = false;

	/**
	 * fill color can be transparent for png
	 *
	 * @var string
	 */

	protected $fill_color = '#000000';

	/**
	 * @see https://secure.php.net/manual/ini.core.php#ini.memory-limit
	 * @var string
	 */
	protected $memory_limit = '-1';

	/**
	 * %1$d - zoom
	 * %2$d - x
	 * %3$d - y
	 *
	 * @see https://secure.php.net/manual/function.sprintf.php
	 * @var string
	 */
	protected $store_structure = '%1$d/%2$d/%3$d';

	/**
	 * determines whether to use fast scaleImage (true) or slow resizeImage (false)
	 *
	 * @var bool
	 */
	protected $fast_resize_upsample = false;

	/**
	 * @see https://secure.php.net/manual/imagick.constants.php
	 * @see http://www.imagemagick.org/Usage/filter/nicolas/
	 * @var int
	 */
	protected $resize_filter_upsample = Imagick::FILTER_ROBIDOUXSHARP;

	/**
	 * @var float
	 */
	protected $resize_blur_upsample = 1.0;

	/**
	 * determines whether to use fast scaleImage (true) or slow resizeImage (false)
	 *
	 * @var bool
	 */
	protected $fast_resize_downsample = false;

	/**
	 * @see https://secure.php.net/manual/imagick.constants.php
	 * @var int
	 */
	protected $resize_filter_downsample = Imagick::FILTER_LANCZOSRADIUS;

	/**
	 * @var float
	 */
	protected $resize_blur_downsample = 1.0;

	/**
	 * image format used for storing the tiles: jpeg or png
	 *
	 * @see http://www.imagemagick.org/script/formats.php
	 *
	 * @var string
	 */
	protected $tile_format = 'png';

	/**
	 * tile image extension - autodetected from format if none given
	 *
	 * @var string
	 */
	protected $tile_ext = null;

	/**
	 * quality of the saved image in jpeg format
	 *
	 * @var int
	 */
	protected $quality_jpeg = 80;

	/**
	 * @var bool
	 */
	protected $overwrite_base_image = false;

	/**
	 * @var bool
	 */
	protected $overwrite_tile_image = false;

	/**
	 * @var bool
	 */
	protected $clean_up = true;

	/**
	 * @var bool
	 */
	protected $optimize_output = false;

	/**
	 * don't create temporary base images
	 *
	 * @var bool
	 */
	protected $no_temp_baseimages = false;

	/**
	 * @param int $zoom_min
	 *
	 * @return void
	 */
	protected function set_zoom_min(int $zoom_min):void{
		$this->zoom_min = max(0, $zoom_min);
	}

	/**
	 * @param int $zoom_max
	 *
	 * @return void
	 */
	protected function set_zoom_max(int $zoom_max):void{
		$this->zoom_max = max(0, $zoom_max);
	}

	/**
	 * @return string
	 */
	protected function get_tile_ext():string{
		return $this->tile_ext ?? $this->getExtension($this->tile_format);
	}

	/**
	 * return file extension depending on given format
	 *
	 * @param string $format
	 *
	 * @return string
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	protected function getExtension(string $format):string{
		$format = strtolower($format);

		if(in_array($format, ['jpeg', 'jp2', 'jpc', 'jxr'], true)){
			return 'jpg';
		}

		if(in_array($format, ['png', 'png00', 'png8', 'png24', 'png32', 'png64'], true)){
			return 'png';
		}

		throw new ImagetilerException('invalid file format');
	}

}
