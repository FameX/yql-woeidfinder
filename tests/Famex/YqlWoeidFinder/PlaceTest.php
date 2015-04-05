<?php
class PlaceTest extends PHPUnit_Framework_TestCase {
	public function testSetGetWoeid(){
		$place = new \Famex\YqlWoeidFinder\Place();
		$place->setWoeid('1234');
		$this->assertSame('1234',$place->getWoeid());
	}
}