<?php
namespace Famex\YqlWoeidFinder;

use Famex\Helpers\LatLongDistHelper;

/**
 * Class YqlWoeidFinder
 * @package Famex\YqlWoeidFinder
 */
class YqlWoeidFinder
{

	/**
	 * @var YqlQueryAdapter
	 */
	protected $adapter;

	/**
	 * @return YqlQueryAdapter
	 */
	public function getAdapter()
	{
		return $this->adapter;
	}

	/**
	 * @param YqlQueryAdapter $adapter
	 */
	public function setAdapter($adapter)
	{
		$this->adapter = $adapter;
	}

    /**
     * @param $lat
     * @param $long
     * @return \Famex\YqlWoeidFinder\Place Place
     */
    public function getPlace($lat, $long)
    {

        $query = sprintf("select * from geo.placefinder where text=\"%s,%s\" and gflags=\"R\"", $lat, $long);

        $place_result = $this->_queryYql($query);

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
		$woeid_result = $this->_queryYql($query);
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
			$city_result = $this->_queryYql($query);
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
			$city_woeid_result = $this->_queryYql($query);
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

	/**
	 * @param $woeid
	 * @return \Famex\YqlWoeidFinder\Place[] Neighbors
	 */
	public function getNeighborsFromWoeid($woeid){
		$query = sprintf("select woeid from geo.places.neighbors where neighbor_woeid = \"%s\"", $woeid);
		$result = $this->_queryYql($query);
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
		$result = $this->_queryYql($query);
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
		$result = $this->_queryYql($query);
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
		if($this->adapter == null){
			$this->adapter = new YqlQueryAdapter();
		}
		return $this->adapter->queryYql($query);
    }

    protected function _latLongDistance($lat1, $lon1, $lat2, $lon2, $unit = "K")
    {
		return LatLongDistHelper::calculate($lat,$lon,$lat2,$lon2,$unit);
    }
}