<?php
/**
 * Class ImagetilerTest
 *
 * @created      20.06.2018
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 */

namespace chillerlan\ImagetilerTest;

use chillerlan\Imagetiler\{Imagetiler, ImagetilerOptions};
use PHPUnit\Framework\TestCase;
use ReflectionClass, ReflectionMethod;

class ImagetilerTest extends TestCase{

	protected ReflectionClass $reflection;

	protected function setUp():void{
		$this->reflection = new ReflectionClass(Imagetiler::class);
	}

	public function testGetSize():void{
		$min = 0;
		$max = 22;

		$options = new ImagetilerOptions([
			'zoom_min'             => $min,
			'zoom_max'             => $max,
			'zoom_normalize'       => 4,
		]);

		$tiler = new Imagetiler($options);

		for($z = $min; $z <= $max; $z++){
			$v = $this->getMethod('getSize')->invokeArgs($tiler, [4096, 2048, $z]);
			/** @phan-suppress-next-line PhanPowerOfZero */
			$expected = 2 ** $z * 256;

			$this->assertSame($expected, $v[0]);
			$this->assertSame($expected/2, $v[1]);
		}

	}

	/**
	 * @param string $method
	 *
	 * @return \ReflectionMethod
	 */
	protected function getMethod(string $method):ReflectionMethod{
		$method = $this->reflection->getMethod($method);
		$method->setAccessible(true);

		return $method;
	}

}
