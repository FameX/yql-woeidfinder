<?php
namespace Famex\YqlWoeidFinder;

use Buzz\Browser;
use Buzz\Client\Curl;
use Famex\WoeidFinder\Adapters\NomatimAdapter;
use Famex\WoeidFinder\Adapters\YqlQueryAdapter;
use Famex\WoeidFinder\Place\WoEID as NewWoeid;
use Famex\WoeidFinder\WoeidFinder;

class YqlWoeidFinder
{
    protected $cache = false;
    protected $browser = false;

    public function getBrowser()
    {
        return $this->browser;
    }

    public function setBrowser($browser)
    {
        $this->browser = $browser;
    }

    public function getCache()
    {
        return $this->cache;
    }

    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param $lat
     * @param $long
     * @return \Famex\YqlWoeidFinder\Place Place
     */
    public function getPlace($lat, $long)
    {
		$woeidFinder = new WoeidFinder();
		$yqlQueryAdapter = new YqlQueryAdapter();
		$yqlQueryAdapter->setCache($this->cache);
		$yqlQueryAdapter->setBrowser($this->browser);
		$nominatimAdapter = new NomatimAdapter();
		$nominatimAdapter->setCache($this->cache);
		$nominatimAdapter->setBrowser($this->browser);
		$woeidFinder->setYqlQueryAdapter($yqlQueryAdapter);
		$woeidFinder->setNomatimAdapter($nominatimAdapter);


		$newPlace = $woeidFinder->getPlace($lat,$long);

		$place = new Place();

		$place->setWoeid($this->_convertWoeid($newPlace->getWoeid()));

		$woeid_types = array(
			'country', 'admin1', 'admin2', 'admin3', 'locality1', 'locality2', 'postal', 'timezone'
		);
		foreach($woeid_types as $woeid_type){
			if(isset($newPlace->$woeid_type)) $place->$woeid_type = $this->_convertWoeid($newPlace->$woeid_type);
		}

		return $place;

    }

	/**
	 * @param $woeid
	 * @return \Famex\YqlWoeidFinder\Place Place
	 */
	public function getPlaceFromWoeid($woeid){
		if((int)$woeid == 0){
			return null;
		}
		$query = sprintf("select * from geo.places where woeid = %s;", $woeid);
		$woeid_result = json_decode($this->_queryYql($query));
		if($woeid_result->query->results == null){
			return null;
		}
		$woeid_result = $woeid_result->query->results->place;

		$place = new Place();

		$woeid = new WoEID();
		$woeid->woeid = $woeid_result->woeid;
		if (isset($woeid_result->boundingBox)) $woeid->boundingBox = $woeid_result->boundingBox;
		if (isset($woeid_result->centroid)) $woeid->centroid = $woeid_result->centroid;
		if (isset($woeid_result->placeTypeName)) $woeid->type = $woeid_result->placeTypeName->content;
		$woeid->content = $woeid_result->name;
		$place->setWoeid($woeid);

		unset($woeid);

		$woeid_types = array(
			'country', 'admin1', 'admin2', 'admin3', 'locality1', 'locality2', 'postal', 'timezone'
		);

		foreach ($woeid_types as $woeid_type) {
			if (isset($woeid_result->$woeid_type)) {
				$woeid = new WoEID();
				if (isset($woeid_result->$woeid_type->code)) $woeid->code = $woeid_result->$woeid_type->code;
				if (isset($woeid_result->$woeid_type->type)) $woeid->type = $woeid_result->$woeid_type->type;
				if (isset($woeid_result->$woeid_type->woeid)) $woeid->woeid = $woeid_result->$woeid_type->woeid;
				if (isset($woeid_result->$woeid_type->content)) $woeid->content = $woeid_result->$woeid_type->content;
				$place->set($woeid_type, $woeid);
				unset($woeid);
			}
		}

		return $place;
	}

	/**
	 * @param $woeid
	 * @return \Famex\YqlWoeidFinder\Place[] Neighbors
	 */
	public function getNeighborsFromWoeid($woeid){
		$query = sprintf("select woeid from geo.places.neighbors where neighbor_woeid = \"%s\"", $woeid);
		$result = json_decode($this->_queryYql($query));
		$neighbors = array();
		$places = array();
		if(($result->query->results == null) || (count($result->query->results->place) < 1)){
			return $neighbors;
		} elseif(count($result->query->results->place) == 1){
			$places[] = $result->query->results->place;
		} else {
			$places = $result->query->results->place;
		}
		foreach($places as $result){
			$neighbors[] = $this->getPlaceFromWoeid($result->woeid);
		}
		return $neighbors;
	}

	/**
	 * @param $woeid
	 * @return \Famex\YqlWoeidFinder\Place[] Siblings
	 */
	public function getSiblingsFromWoeid($woeid){
		$query = sprintf("select woeid from geo.places.siblings where sibling_woeid = \"%s\"", $woeid);
		$result = json_decode($this->_queryYql($query));
		$siblings = array();
		$places = array();
		if(($result->query->results == null) || (count($result->query->results->place) < 1)){
			return $siblings;
		} elseif(count($result->query->results->place) == 1){
			$places[] = $result->query->results->place;
		} else {
			$places = $result->query->results->place;
		}
		foreach($places as $result){
			$siblings[] = $this->getPlaceFromWoeid($result->woeid);
		}
		return $siblings;
	}

	/**
	 * @param $woeid
	 * @return \Famex\YqlWoeidFinder\Place[] Children
	 */
	public function getChildrenFromWoeid($woeid){
		$query = sprintf("select woeid from geo.places.children where parent_woeid = \"%s\"", $woeid);
		$result = json_decode($this->_queryYql($query));
		$children = array();
		$places = array();
		if(($result->query->results == null) || (count($result->query->results->place) < 1)){
			return $children;
		} elseif(count($result->query->results->place) == 1){
			$places[] = $result->query->results->place;
		} else {
			$places = $result->query->results->place;
		}
		foreach($places as $result){
			$children[] = $this->getPlaceFromWoeid($result->woeid);
		}
		return $children;
	}

	protected function _queryYql($query)
    {
        if ($this->browser === false) {
            $this->browser = new Browser();
            $client = new Curl();
			$client->setTimeout(30);
            $this->browser->setClient($client);
        }
        $key = "yql-query-" . md5($query);
        if (($this->cache != false) && ($result = $this->cache->get($key))) {
            return $result;
        }
        $yqlquery = sprintf("https://query.yahooapis.com/v1/public/yql?q=%s&format=json", urlencode($query));
        $result = $this->browser->get($yqlquery)->getContent();
        if ($this->cache != false) {
            $this->cache->put($key, $result, 60);
        }
        return $result;
    }

	protected function _convertWoeid(NewWoeid $newWoeid){
		$oldWoeid = new WoEID();
		if (isset($newWoeid->code)) $oldWoeid->code = $newWoeid->code;
		if (isset($newWoeid->type)) $oldWoeid->type = $newWoeid->type;
		if (isset($newWoeid->woeid)) $oldWoeid->woeid = $newWoeid->woeid;
		if (isset($newWoeid->content)) $oldWoeid->content = $newWoeid->content;
		if (isset($newWoeid->centroid)) $oldWoeid->centroid = $newWoeid->centroid;
		if (isset($newWoeid->boundingBox)) $oldWoeid->boundingBox = $newWoeid->boundingBox;

		return $oldWoeid;
	}

    protected function _latLongDistance($lat1, $lon1, $lat2, $lon2, $unit = "K")
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);
        if ($unit == "K") {
            return ($miles * 1.609344);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }
}