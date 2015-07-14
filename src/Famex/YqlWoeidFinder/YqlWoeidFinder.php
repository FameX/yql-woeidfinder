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

        $query = sprintf("select * from geo.placefinder where text=\"%s,%s\" and gflags=\"R\"", $lat, $long);

        $place_result = json_decode($this->_queryYql($query));

		if($place_result->query->results == null){
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

		if (!isset($place->locality1) && (isset($place_result->city)) && (isset($place_result->country))) {
			$query = sprintf("select * from geo.placefinder where text=\"%s, %s\"", $place_result->city, $place_result->country);
			$city_result = json_decode($this->_queryYql($query));
			$mindist = 420000;
			$minkey = 0;
			$city_results = $city_result->query->results->Result;
			if(!is_array($city_results)){
				$city_results = array($city_results);
			}
			foreach ($city_results as $key => $value) {
				$dist = $this->_latLongDistance($lat,$long,$value->latitude,$value->longitude);
				if($dist < $mindist){
					$minkey = $key;
					$mindist = $dist;
				}
			}
			$city_result = $city_results[$minkey];
			$query = sprintf("select * from geo.places where woeid = %s;", $city_result->woeid);
			$city_woeid_result = json_decode($this->_queryYql($query));
			$city_woeid_result = $city_woeid_result->query->results->place;

			$okay_codes = array(7, 22, 10);

			if (
				isset($city_woeid_result->placeTypeName) &&
				isset($city_woeid_result->placeTypeName->code) &&
				in_array($city_woeid_result->placeTypeName->code, $okay_codes)
			) {
				$woeid = new WoEID();
				$woeid->woeid = $city_woeid_result->woeid;
				if (isset($city_woeid_result->boundingBox)) $woeid->boundingBox = $city_woeid_result->boundingBox;
				if (isset($city_woeid_result->centroid)) $woeid->centroid = $city_woeid_result->centroid;
				if (isset($city_woeid_result->placeTypeName)) $woeid->type = $city_woeid_result->placeTypeName->content;
				$woeid->content = $city_woeid_result->name;


				$place->locality1 = $woeid;
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