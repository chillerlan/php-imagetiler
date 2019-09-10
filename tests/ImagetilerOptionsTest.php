<?php
/**
 * Class ImagetilerOptionsTest
 *
 * @filesource   ImagetilerOptionsTest.php
 * @created      09.09.2019
 * @package      chillerlan\ImagetilerTest
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2019 smiley
 * @license      MIT
 */

namespace chillerlan\ImagetilerTest;

use chillerlan\Imagetiler\ImagetilerOptions;
use PHPUnit\Framework\TestCase;

class ImagetilerOptionsTest extends TestCase{

	public function testSetMinMaxZoom(){
		$options = new ImagetilerOptions;

		// clamp negative values
		$options->zoom_min = -1;
		$this->assertSame(0, $options->zoom_min);

		$options->zoom_max = -1;
		$this->assertSame(0, $options->zoom_max);
	}

}
