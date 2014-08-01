<?php
namespace Famex\YqlWoeidFinder;


class Place {
    protected $woeid = false;

    /**
     * @param string $type
     * @param \Famex\YqlWoeidFinder\Woeid $type
     */
    public function set($type,$woeid){
        $this->$type = $woeid;
    }

    /**
     * @return \Famex\YqlWoeidFinder\Woeid|boolean
     */
    public function getWoeid()
    {
        return $this->woeid;
    }

    /**
     * @param \Famex\YqlWoeidFinder\Woeid $woeid
     */
    public function setWoeid($woeid)
    {
        $this->woeid = $woeid;
    }
}