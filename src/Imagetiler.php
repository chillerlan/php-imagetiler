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
use Imagick;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait, LoggerInterface, NullLogger};

class Imagetiler implements LoggerAwareInterface{
	use LoggerAwareTrait;

	/**
	 * @var string
	 */
	protected $ext;

	/**
	 * @var \chillerlan\Imagetiler\ImagetilerOptions
	 */
	protected $options;

	/**
	 * Imagetiler constructor.
	 *
	 * @param \chillerlan\Traits\ContainerInterface|null $options
	 * @param \Psr\Log\LoggerInterface|null              $logger
	 *
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	public function __construct(ContainerInterface $options = null, LoggerInterface $logger = null){

		if(!extension_loaded('imagick')){
			throw new ImagetilerException('Imagick extension is not available');
		}

		$this->setOptions($options ?? new ImagetilerOptions);
		$this->setLogger($logger ?? new NullLogger);
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

		$this->ext = $this->getExtension();

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

		// prepare base images for each zoom level
		$this->prepareZoomBaseImages($image_path, $out_path);

		// create tiles for each zoom level
		for($i = $this->options->zoom_min; $i <= $this->options->zoom_max; $i++){
			$this->createTilesForZoom($out_path, $i);
		}

		// clean up base images
		if($this->options->clean_up){
			$this->removeZoomBaseImages($out_path);
		}

		return $this;
	}

	/**
	 * prepare each zoom lvl base images
	 *
	 * @param string $image_path
	 * @param string $out_path
	 */
	protected function prepareZoomBaseImages(string $image_path, string $out_path):void{

		//load main image
		$im = new Imagick($image_path);
		$im->setImageFormat($this->options->tile_format);

		//get image size
		$width  = $im->getimagewidth();
		$height = $im->getImageHeight();

		$this->logger->info('input image loaded: ['.$width.'x'.$height.'] '.$image_path);

		//prepare each zoom lvl base images
		$start = true;
		$il    = null;

		for($zoom = $this->options->zoom_max; $zoom >= $this->options->zoom_min; $zoom--){
			$base_image = $out_path.'/'.$zoom.'.'.$this->ext;

			//check if already exist
			if(!$this->options->overwrite_base_image && is_file($base_image)){
				$this->logger->info('base image for zoom level '.$zoom.' already exists: '.$base_image);
				continue;
			}

			[$w, $h] = $this->getSize($width, $height, $zoom);

			//fit main image to current zoom lvl
			$il = $start ? clone $im : $il;

			$this->options->fast_resize === true
				// scaleImage - works fast, but without any quality configuration
				? $il->scaleImage($w, $h, true)
				// resizeImage - works slower but offers better quality
				: $il->resizeImage($w, $h, $this->options->resize_filter, $this->options->resize_blur);

			//store
			$this->imageSave($il, $base_image);

			//clear
			if($start){
				$im->clear();
				$im->destroy();
			}

			$start = false;
			$this->logger->info('created image for zoom level '.$zoom.' ['.$w.'x'.$h.'] '.$base_image);
		}

		//free resurce, destroy imagick object
		if($il instanceof Imagick){
			$il->clear();
			$il->destroy();
		}
	}

	/**
	 * @param string $out_path
	 * @param int    $zoom
	 *
	 * @return void
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	protected function createTilesForZoom(string $out_path, int $zoom):void{
		$base_image = $out_path.'/'.$zoom.'.'.$this->ext;

		//load image
		if(!is_file($base_image) || !is_readable($base_image)){
			throw new ImagetilerException('cannot read base image '.$base_image.' for zoom '.$zoom);
		}

		$im = new Imagick($base_image);

		//get image size
		$w = $im->getimagewidth();
		$h = $im->getImageHeight();

		$ts = $this->options->tile_size;

		$x = (int)ceil($w / $ts);
		$y = (int)ceil($h / $ts);

		// width
		for($ix = 0; $ix < $x; $ix++){
			$cx = $ix * $ts;

			// height
			for($iy = 0; $iy < $y; $iy++){
				$tile = $out_path.'/'.sprintf($this->options->store_structure, $zoom, $ix, $iy).'.'.$this->ext;

				// check if tile already exists
				if(!$this->options->overwrite_tile_image && is_file($tile)){
					$this->logger->info('tile '.$zoom.'/'.$x.'/'.$y.' already exists: '.$tile);

					continue;
				}

				$ti = clone $im;

				$cy = $this->options->tms
					? $h - ($iy + 1) * $ts
					: $iy * $ts;

				$ti->cropImage($ts, $ts, $cx, $cy);

				// check if the current tile is smaller than the tile size (leftover edges on the input image)
				if($ti->getImageWidth() < $ts || $ti->getimageheight() < $ts){

					$th = $this->options->tms
						? $im->getImageHeight() - $ts
						: 0;

					$ti->setImageBackgroundColor($this->options->fill_color);
					$ti->extentImage($ts, $ts, 0, $th);
				}

				// save
				$this->imageSave($ti, $tile);

				$ti->clear();
				$ti->destroy();
			}
		}

		// clear resources
		$im->clear();
		$im->destroy();

		$this->logger->info('created tiles for zoom level: '.$zoom);
	}

	/**
	 * remove zoom lvl base images
	 *
	 * @param string $out_path
	 *
	 * @return void
	 */
	protected function removeZoomBaseImages(string $out_path):void{

		for($i = $this->options->zoom_min; $i <= $this->options->zoom_max; $i++){
			$lvl_file = $out_path.'/'.$i.'.'.$this->ext;

			if(is_file($lvl_file)){
				if(unlink($lvl_file)){
					$this->logger->info('deleted base image for zoom level '.$i.': '.$lvl_file);
				}
			}
		}

	}

	/**
	 * save image in to destination
	 *
	 * @param Imagick $image
	 * @param string  $dest full path with file name
	 *
	 * @return void
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	protected function imageSave(Imagick $image, string $dest):void{

		//prepare folder
		$dir = dirname($dest);

		if(!is_dir($dir)){
			if(!mkdir($dir, 0755, true)){
				throw new ImagetilerException('cannot crate folder '.$dir);
			}
		}

		//prepare to save
		if($this->options->tile_format === 'jpeg'){
			$image->setCompression(Imagick::COMPRESSION_JPEG);
			$image->setCompressionQuality($this->options->quality_jpeg);
		}

		//save image
		if(!$image->writeImage($dest)){
			throw new ImagetilerException('cannot save image '.$dest);
		}

	}


	/**
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
	 * @return string
	 * @throws \chillerlan\Imagetiler\ImagetilerException
	 */
	protected function getExtension():string{
		$fmt = strtolower($this->options->tile_format);

		if(in_array($fmt, ['jpeg', 'jp2', 'jpc', 'jxr',], true)){
			return 'jpg';
		}

		if(in_array(
			$fmt, ['png', 'png00', 'png8', 'png24', 'png32', 'png64',], true)){
			return 'png';
		}

		throw new ImagetilerException('invalid file format');
	}

}
