<?php
/**
 * Class ImagetilerOptions
 *
 * @filesource   ImagetilerOptions.php
 * @created      20.06.2018
 * @package      chillerlan\Imagetiler
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 */

namespace chillerlan\Imagetiler;

use chillerlan\Settings\SettingsContainerAbstract;

/**
 * @property int $tile_size
 * @property int $zoom_min
 * @property int $zoom_max
 * @property int $zoom_normalize
 * @property bool $tms
 * @property string $fill_color
 * @property string $memory_limit
 * @property string $store_structure
 * @property bool $fast_resize
 * @property int $resize_filter
 * @property float $resize_blur
 * @property string $tile_format
 * @property string $tile_ext
 * @property int $quality_jpeg
 * @property string $imagick_tmp
 * @property bool $overwrite_base_image
 * @property bool $overwrite_tile_image
 * @property bool $clean_up
 * @property bool $optimize_output
 */
class ImagetilerOptions extends SettingsContainerAbstract{
	use ImagetilerOptionsTrait;
}
