<?php
/**
 * Class Imagetiler
 *
 * @filesource   Imagetiler.php
 * @created      20.06.2018
 * @package      chillerlan\Imagetiler
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 */

namespace chillerlan\Imagetiler;

use chillerlan\Settings\SettingsContainerInterface;
use ImageOptimizer\Optimizer;
use Imagick;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait, LoggerInterface, NullLogger};

use function ceil, dirname, extension_loaded, function_exists, ini_get, ini_set, is_dir,
	is_file, is_readable, is_writable, mkdir, putenv, round, sprintf, unlink;

class Imagetiler implements LoggerAwareInterface{
	use LoggerAwareTrait;

	/** @var \chillerlan\Imagetiler\ImagetilerOptions */
	protected SettingsContainerInterface $options;
	protected ?Optimizer $optimizer = null;

	/**
	 * Imagetiler constructor.
	 *
	 * @param \chillerlan\Settings\SettingsContainerInterface|null $options
	 * @param \ImageOptimizer\Optimizer                            $optimizer
	 * @param \Psr\Log\LoggerInterface|null                        $logger
	 *
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	public function __construct(SettingsContainerInterface $options = null, Optimizer $optimizer = null, LoggerInterface $logger = null){

		if(!extension_loaded('imagick')){
			throw new ImagetilerException('Imagick extension is not available');
		}

		$this->setOptions($options ?? new ImagetilerOptions);
		$this->setLogger($logger ?? new NullLogger);

		if($optimizer instanceof Optimizer){
			$this->setOptimizer($optimizer);
		}
	}

	/**
	 * @param \chillerlan\Settings\SettingsContainerInterface $options
	 *
	 * @return \chillerlan\Imagetiler\Imagetiler
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	public function setOptions(SettingsContainerInterface $options):Imagetiler{
		$this->options = $options;

		if(ini_set('memory_limit', $this->options->memory_limit) === false){
			throw new ImagetilerException('could not alter ini settings');
		}

		if(ini_get('memory_limit') !== (string)$this->options->memory_limit){
			throw new ImagetilerException('ini settings differ from options');
		}

		return $this;
	}

	/**
	 * @param \ImageOptimizer\Optimizer $optimizer
	 *
	 * @return \chillerlan\Imagetiler\Imagetiler
	 */
	public function setOptimizer(Optimizer $optimizer):Imagetiler{
		$this->optimizer = $optimizer;

		return $this;
	}

	/**
	 * @param string $image_path
	 * @param string $out_path
	 *
	 * @return \chillerlan\Imagetiler\Imagetiler
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	public function process(string $image_path, string $out_path):Imagetiler{

		if(!is_file($image_path) || !is_readable($image_path)){
			throw new ImagetilerException('cannot read image '.$image_path);
		}

		if(!is_dir($out_path) || !is_writable($out_path)){

			if(!mkdir($out_path, 0755, true)){
				throw new ImagetilerException('output path is not writable');
			}

		}

		$this->logger->info('processing image: '.$image_path.', out path: '.$out_path);

		// prepare the zoom base images
		$base_images = $this->prepareZoomBaseImages($image_path, $out_path);

		if($this->options->no_temp_baseimages === true){
			return $this;
		}

		// create the tiles
		foreach($base_images as $zoom => $base_image){

			//load image
			if(!is_file($base_image) || !is_readable($base_image)){
				throw new ImagetilerException('cannot read base image '.$base_image.' for zoom '.$zoom);
			}

			$this->createTilesForZoom(new Imagick($base_image), $zoom, $out_path);
		}

		// clean up base images
		if($this->options->clean_up){

			for($zoom = $this->options->zoom_min; $zoom <= $this->options->zoom_max; $zoom++){
				$lvl_file = $out_path.'/'.$zoom.'.'.$this->options->tile_ext;

				if(is_file($lvl_file)){
					if(unlink($lvl_file)){
						$this->logger->info('deleted base image for zoom level '.$zoom.': '.$lvl_file);
					}
				}
			}

		}

		return $this;
	}

	/**
	 * prepare base images for each zoom level
	 *
	 * @param string $image_path
	 * @param string $out_path
	 *
	 * @return array
	 */
	protected function prepareZoomBaseImages(string $image_path, string $out_path):array{
		$base_images = [];

		// create base image file names
		for($zoom = $this->options->zoom_max; $zoom >= $this->options->zoom_min; $zoom--){
			$base_image = $out_path.'/'.$zoom.'.'.$this->options->tile_ext;
			// check if the base image already exists
			if(!$this->options->overwrite_base_image && is_file($base_image)){
				$this->logger->info('base image for zoom level '.$zoom.' already exists: '.$base_image);

				continue;
			}

			$base_images[$zoom] = $base_image;
		}

		if(empty($base_images)){
			return [];
		}

		$im = new Imagick($image_path);
		$im->setColorspace(Imagick::COLORSPACE_SRGB);
		$im->setImageFormat($this->options->tile_format);

		$width  = $im->getimagewidth();
		$height = $im->getImageHeight();

		$this->logger->info('input image loaded: ['.$width.'x'.$height.'] '.$image_path);

		foreach($base_images as $zoom => $base_image){
			[$w, $h] = $this->getSize($width, $height, $zoom);

			// clone the original image and fit it to the current zoom level
			$il = clone $im;

			if($zoom > $this->options->zoom_normalize){
				$this->scale($il, $w, $h, $this->options->fast_resize_upsample, $this->options->resize_filter_upsample, $this->options->resize_blur_upsample);
			}
			elseif($zoom < $this->options->zoom_normalize){
				$this->scale($il, $w, $h, $this->options->fast_resize_downsample, $this->options->resize_filter_downsample, $this->options->resize_blur_downsample);
			}

			$this->options->no_temp_baseimages === false
				? $this->saveImage($il, $base_image, false)
				: $this->createTilesForZoom($il, $zoom, $out_path);

			$this->clearImage($il);

			$this->logger->info('created image for zoom level '.$zoom.' ['.$w.'x'.$h.'] '.$base_image);
		}

		$this->clearImage($im);

		return $base_images;
	}

	/**
	 * @param \Imagick $im
	 * @param int      $w
	 * @param int      $h
	 * @param bool     $fast
	 * @param int      $filter
	 * @param float    $blur
	 *
	 * @return void
	 */
	protected function scale(Imagick $im, int $w, int $h, bool $fast, int $filter, float $blur):void{
		$fast === true
			// scaleImage - works fast, but without any quality configuration
			? $im->scaleImage($w, $h, true)
			// resizeImage - works slower but offers better quality
			: $im->resizeImage($w, $h, $filter, $blur);
	}

	/**
	 * create tiles for each zoom level
	 *
	 * @param \Imagick $im
	 * @param int      $zoom
	 * @param string   $out_path
	 *
	 * @return void
	 */
	protected function createTilesForZoom(Imagick $im, int $zoom, string $out_path):void{
		$im->setColorspace(Imagick::COLORSPACE_SRGB);
		$ts = $this->options->tile_size;
		$h  = $im->getImageHeight();
		$x  = (int)ceil($im->getimagewidth() / $ts);
		$y  = (int)ceil($h / $ts);

		// width
		for($ix = 0; $ix < $x; $ix++){
			$cx = $ix * $ts;

			// create a stripe tile_size * height
			$ci = clone $im;
			$ci->cropImage($ts, $h, $cx, 0);

			// height
			for($iy = 0; $iy < $y; $iy++){
				$tile = $out_path.'/'.sprintf($this->options->store_structure, $zoom, $ix, $iy).'.'.$this->options->tile_ext;

				// check if tile already exists
				if(!$this->options->overwrite_tile_image && is_file($tile)){
					$this->logger->info('tile '.$zoom.'/'.$ix.'/'.$iy.' already exists: '.$tile);

					continue;
				}

				// cut the stripe into pieces of height = tile_size
				$cy = $this->options->tms
					? $h - ($iy + 1) * $ts
					: $iy * $ts;

				$ti = clone $ci;
				$ti->setImagePage(0, 0, 0, 0);
				$ti->cropImage($ts, $ts, 0, $cy);

				// check if the current tile is smaller than the tile size (leftover edges on the input image)
				if($ti->getImageWidth() < $ts || $ti->getimageheight() < $ts){

					$th = $this->options->tms ? $h - $ts : 0;

					$ti->setImageBackgroundColor($this->options->fill_color);
					$ti->extentImage($ts, $ts, 0, $th);
				}

				$this->saveImage($ti, $tile, true);
				$this->clearImage($ti);
			}

			$this->clearImage($ci);
			$this->logger->info('created column '.$ix.', zoom = '.$zoom.', x = '.$cx);
		}

		$this->clearImage($im);
		$this->logger->info('created tiles for zoom level: '.$zoom.', '.$x.' columns, '.$y.' tile(s) per column');
	}

	/**
	 * save image in to destination
	 *
	 * @param Imagick $image
	 * @param string  $dest full path with file name
	 * @param bool    $optimize
	 *
	 * @return void
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	protected function saveImage(Imagick $image, string $dest, bool $optimize):void{
		$dir = dirname($dest);

		if(!is_dir($dir)){
			if(!mkdir($dir, 0755, true)){
				throw new ImagetilerException('cannot create folder '.$dir);
			}
		}

		if($this->options->tile_format === 'jpeg'){
			$image->setCompression(Imagick::COMPRESSION_JPEG2000);
			$image->setCompressionQuality($this->options->quality_jpeg);
		}

		if(!$image->writeImage($dest)){
			throw new ImagetilerException('cannot save image '.$dest);
		}

		if($this->options->optimize_output && $optimize && $this->optimizer instanceof Optimizer){
			$this->optimizer->optimize($dest);
		}

	}

	/**
	 * free resources, destroy imagick object
	 *
	 * @param \Imagick|null $image
	 *
	 * @return bool
	 */
	protected function clearImage(Imagick $image = null):bool{

		if($image instanceof Imagick){
			$image->clear();

			return $image->destroy();
		}

		return false;
	}

	/**
	 * calculate the image size for the given zoom level
	 *
	 * @param int $width
	 * @param int $height
	 * @param int $zoom
	 *
	 * @return int[]
	 */
	protected function getSize(int $width, int $height, int $zoom):array{
		$zoom_normalize = $this->options->zoom_normalize ?? $this->options->zoom_max;

		if($this->options->zoom_max > $zoom_normalize && $zoom > $zoom_normalize){
			$zd = 2 ** ($zoom - $zoom_normalize);

			return [$zd * $width, $zd * $height];
		}

		if($zoom < $zoom_normalize){
			$zd = 2 ** ($zoom_normalize - $zoom);

			return [(int)round($width / $zd), (int)round($height / $zd)];
		}

		return [$width, $height];
	}

}
