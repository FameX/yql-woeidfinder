<?php
class YqlWoeidFinderTest extends PHPUnit_Framework_TestCase {
	protected $yqlWoeidFinder;

	public function setup(){
		$this->yqlWoeidFinder = new \Famex\YqlWoeidFinder\YqlWoeidFinder();
	}


	public function testGetPlace(){
		try {
			$place = $this->yqlWoeidFinder->getPlace(3.1578500,101.7116500);
			$this->assertInstanceOf('Famex\YqlWoeidFinder\Place',$place,"Did not return a place object");
			$this->assertInstanceOf('Famex\YqlWoeidFinder\WoEID',$place->getWoeid(),"The place object does not have a woeid object");
			$this->assertEquals('28347326',$place->getWoeid()->woeid,'The place object has a wrong woeid for this lat/long');

			$this->setExpectedException('Exception');
			$place = $this->yqlWoeidFinder->getPlace(0,0);
		} catch (Buzz\Exception\RequestException $e){
			$this->markTestSkipped(
				'Unable to connect to the YQL service.'
			);
		}

	}

	public function testGetSecondPlace(){
		try {
			$place = $this->yqlWoeidFinder->getPlace(53.5512,10);
			$this->assertInstanceOf('Famex\YqlWoeidFinder\Place',$place,"Did not return a place object");
			$this->assertInstanceOf('Famex\YqlWoeidFinder\WoEID',$place->getWoeid(),"The place object does not have a woeid object");
			$this->assertEquals('12832451',$place->getWoeid()->woeid,'The place object has a wrong woeid for this lat/long');

			$this->setExpectedException('Exception');
			$place = $this->yqlWoeidFinder->getPlace(0,0);
		} catch (Buzz\Exception\RequestException $e){
			$this->markTestSkipped(
				'Unable to connect to the YQL service.'
			);
		}

	}

	public function testGetPlaceFromWoeid(){
		try {
			$place = $this->yqlWoeidFinder->getPlaceFromWoeid('28347326');
			$this->assertInstanceOf('Famex\YqlWoeidFinder\Place',$place,"Did not return a place object");
			$this->assertInstanceOf('Famex\YqlWoeidFinder\WoEID',$place->getWoeid(),"The place object does not have a woeid object");

			$this->assertInstanceOf('Famex\YqlWoeidFinder\WoEID',$place->timezone,"The place object does not have a propoer timezone");
			$this->assertEquals('Asia/Kuala_Lumpur',$place->timezone->content,"The place object has the wrong timezone");

			$place = $this->yqlWoeidFinder->getPlaceFromWoeid('0');
			$this->assertNull($place,"This returns something when it shouldn't");

		} catch (Buzz\Exception\RequestException $e){
			$this->markTestSkipped(
				'Unable to connect to the YQL service.'
			);
		}
	}
}