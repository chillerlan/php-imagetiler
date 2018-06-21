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

use chillerlan\Traits\ContainerInterface;
use ImageOptimizer\Optimizer;
use Imagick;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait, LoggerInterface, NullLogger};

class Imagetiler implements LoggerAwareInterface{
	use LoggerAwareTrait;

	/**
	 * @var \chillerlan\Imagetiler\ImagetilerOptions
	 */
	protected $options;

	/**
	 * @var \ImageOptimizer\Optimizer
	 */
	protected $optimizer;

	/**
	 * Imagetiler constructor.
	 *
	 * @param \chillerlan\Traits\ContainerInterface|null $options
	 * @param \ImageOptimizer\Optimizer                  $optimizer
	 * @param \Psr\Log\LoggerInterface|null              $logger
	 *
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	public function __construct(ContainerInterface $options = null, Optimizer $optimizer = null, LoggerInterface $logger = null){

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
	 * @param \chillerlan\Traits\ContainerInterface $options
	 *
	 * @return \chillerlan\Imagetiler\Imagetiler
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	public function setOptions(ContainerInterface $options):Imagetiler{
		$options->zoom_min = max(0, $options->zoom_min);
		$options->zoom_max = max(1, $options->zoom_max);

		if($options->zoom_normalize === null || $options->zoom_max < $options->zoom_normalize){
			$options->zoom_normalize = $options->zoom_max;
		}

		if($options->tile_ext === null){
			$options->tile_ext = $this->getExtension($options->tile_format);
		}

		$this->options = $options;

		if(ini_set('memory_limit', $this->options->memory_limit) === false){
			throw new ImagetilerException('could not alter ini settings');
		}

		if(ini_get('memory_limit') !== (string)$this->options->memory_limit){
			throw new ImagetilerException('ini settings differ from options');
		}

		if($this->options->imagick_tmp !== null && is_dir($this->options->imagick_tmp)){
			apache_setenv('MAGICK_TEMPORARY_PATH', $this->options->imagick_tmp);
			putenv('MAGICK_TEMPORARY_PATH='.$this->options->imagick_tmp);
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

		if(!is_dir($out_path)|| !is_writable($out_path)){

			if(!mkdir($out_path, 0755, true)){
				throw new ImagetilerException('output path is not writable');
			}

		}

		// prepare the zoom base images
		$this->prepareZoomBaseImages($image_path, $out_path);

		// create the tiles
		for($zoom = $this->options->zoom_min; $zoom <= $this->options->zoom_max; $zoom++){

			$base_image = $out_path.'/'.$zoom.'.'.$this->options->tile_ext;

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
	 */
	protected function prepareZoomBaseImages(string $image_path, string $out_path):void{
		$im = new Imagick($image_path);
		$im->setImageFormat($this->options->tile_format);

		$width  = $im->getimagewidth();
		$height = $im->getImageHeight();

		$this->logger->info('input image loaded: ['.$width.'x'.$height.'] '.$image_path);

		$start = true;
		$il    = null;

		for($zoom = $this->options->zoom_max; $zoom >= $this->options->zoom_min; $zoom--){
			$base_image = $out_path.'/'.$zoom.'.'.$this->options->tile_ext;

			// check if the base image already exists
			if(!$this->options->overwrite_base_image && is_file($base_image)){
				$this->logger->info('base image for zoom level '.$zoom.' already exists: '.$base_image);
				continue;
			}

			[$w, $h] = $this->getSize($width, $height, $zoom);

			// fit main image to current zoom level
			$il = $start ? clone $im : $il;

			$this->options->fast_resize === true
				// scaleImage - works fast, but without any quality configuration
				? $il->scaleImage($w, $h, true)
				// resizeImage - works slower but offers better quality
				: $il->resizeImage($w, $h, $this->options->resize_filter, $this->options->resize_blur);

			// save without optimizing
			$this->saveImage($il, $base_image, false);

			if($start){
				$this->clearImage($im);
			}

			$start = false;
			$this->logger->info('created image for zoom level '.$zoom.' ['.$w.'x'.$h.'] '.$base_image);
		}

		$this->clearImage($il);
	}

	/**
	 * create tiles for each zoom level
	 *
	 * @param \Imagick                       $im
	 * @param int                            $zoom
	 * @param string                         $out_path
	 *
	 * @return void
	 */
	protected function createTilesForZoom(Imagick $im, int $zoom, string $out_path):void{
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
					$this->logger->info('tile '.$zoom.'/'.$x.'/'.$y.' already exists: '.$tile);

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
			$this->logger->info('created column '.($ix+1).', x = '.$cx);
		}

		$this->clearImage($im);
		$this->logger->info('created tiles for zoom level: '.$zoom.', '.$y.' tile(s) per column');
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
				throw new ImagetilerException('cannot crate folder '.$dir);
			}
		}

		if($this->options->tile_format === 'jpeg'){
			$image->setCompression(Imagick::COMPRESSION_JPEG);
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

		if($this->options->zoom_max > $this->options->zoom_normalize && $zoom > $this->options->zoom_normalize){
			$zd = 2 ** ($zoom - $this->options->zoom_normalize);

			return [$zd * $width, $zd * $height];
		}

		if($zoom < $this->options->zoom_normalize){
			$zd = 2 ** ($this->options->zoom_normalize - $zoom);

			return [(int)round($width / $zd), (int)round($height / $zd)];
		}

		return [$width, $height];
	}

	/**
	 * return file extension depend of given format
	 *
	 * @param string $format
	 *
	 * @return string
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	protected function getExtension(string $format):string{

		if(in_array($format, ['jpeg', 'jp2', 'jpc', 'jxr',], true)){
			return 'jpg';
		}

		if(in_array($format, ['png', 'png00', 'png8', 'png24', 'png32', 'png64',], true)){
			return 'png';
		}

		throw new ImagetilerException('invalid file format');
	}

}
