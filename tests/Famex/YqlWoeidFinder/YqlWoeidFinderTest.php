<?php
class YqlWoeidFinderTest extends PHPUnit_Framework_TestCase {
	protected $yqlWoeidFinder;

	public function setup(){
		$this->yqlWoeidFinder = new \Famex\YqlWoeidFinder\YqlWoeidFinder();
	}


	public function testGetPlace(){
		// $finderStub = $this->getMockBuilder('Famex\YqlWoeidFinder\YqlWoeidFinder')->getMock();
		// 3.1578500,101.7116500
		$place = $this->yqlWoeidFinder->getPlace(3.1578500,101.7116500);
		$this->assertInstanceOf('Famex\YqlWoeidFinder\Place',$place);
		$this->assertInstanceOf('Famex\YqlWoeidFinder\WoEID',$place->getWoeid());
		$this->assertEquals('28347326',$place->getWoeid()->woeid);

		$this->setExpectedException('Exception');
		$place = $this->yqlWoeidFinder->getPlace(0,0);

	}
}