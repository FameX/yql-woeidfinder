<?php
namespace Famex\YqlWoeidFinder;

use Buzz\Browser;
use Buzz\Client\Curl;

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
		// This is probably the silliest thing in the world. But for some reason YQL breaks for completely round numbers.
		if(!strpos($lat,".",0)){
			$lat = $lat . ".0";
		}
		if(!strpos($long,".",0)){
			$long = $long . ".0";
		}

        $query = sprintf("select * from geo.placefinder where text=\"%s,%s\" and gflags=\"R\"", $lat, $long);

        $place_result = json_decode($this->_queryYql($query));

		if(!isset($place_result->query) || ($place_result->query->results == null)){
			throw new \Exception();
		}

        $place_result = $place_result->query->results->Result;

        if (!isset($place_result->woeid)) {
            throw new \Exception();
        }

		return self::getPlaceFromWoeid($place_result->woeid);

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