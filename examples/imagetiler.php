<?php
/**
 * @filesource   imagetiler.php
 * @created      20.06.2018
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 */

namespace chillerlan\ImagetilerExamples;

use chillerlan\Imagetiler\{Imagetiler, ImagetilerException, ImagetilerOptionsTrait};
use chillerlan\Logger\{Log, LogOptionsTrait, Output\ConsoleLog};
use chillerlan\Traits\ContainerAbstract;

require_once __DIR__.'/../vendor/autoload.php';

$input = __DIR__.'/[YOUR HUGE IMAGE].png';

$options = [
	// ImagetilerOptions
	'zoom_min'             => 0,
	'zoom_max'             => 8,
	'zoom_normalize'       => 6,
	'tms'                  => false,
	'fill_color'           => '#000000',
	'fast_resize'          => false,
	'overwrite_base_image' => false,
	'overwrite_tile_image' => true,
	'clean_up'             => false,
	// LogOptions
	'minLogLevel' => 'debug',
];

$options = new class($options) extends ContainerAbstract{
	use ImagetilerOptionsTrait, LogOptionsTrait;
};

$logger = (new Log)->addInstance(new ConsoleLog($options), 'console');
$tiler  = new Imagetiler($options, $logger);

try{
	$tiler->process($input, __DIR__.'/tiles');
}
catch(ImagetilerException $e){
	echo $e->getMessage();
	echo $e->getTraceAsString();
}
