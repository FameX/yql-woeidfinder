<?php
namespace Famex\YqlWoeidFinder;

use Buzz\Browser;
use Buzz\Client\Curl;
use Famex\YqlWoeidFinder\YqlQueryAdapter\Exception;

/**
 * Class YqlQueryAdapter
 * @package Famex\YqlWoeidFinder
 */
class YqlQueryAdapter {
	/**
	 * @var \ArrayStore
	 */
	protected $cache;

	/**
	 * @var \Buzz\Browser
	 */
	protected $browser;

	/**
	 * @return \Buzz\Browser
	 */
	public function getBrowser()
	{
		return $this->browser;
	}

	/**
	 * @param \Buzz\Browser $browser
	 */
	public function setBrowser($browser)
	{
		$this->browser = $browser;
	}

	/**
	 * @return \ArrayStore
	 */
	public function getCache()
	{
		return $this->cache;
	}

	/**
	 * @param \ArrayStore $cache
	 */
	public function setCache($cache)
	{
		$this->cache = $cache;
	}

	/**
	 * @param $query
	 * @return stdClass
	 * @throws Exception
	 */
	public function queryYql($query)
	{
		if($this->browser == null){
			$this->browser = new Browser();
			$curl = new Curl();
			$this->browser->setClient($curl);
		}

		$yqlquery = sprintf("https://query.yahooapis.com/v1/public/yql?q=%s&format=json", urlencode($query));

		$key = "YqlQueryAdapter-query-" . md5($query);
		if (($this->cache != false) && ($content = $this->cache->get($key))) {
			return json_decode($content);
		}

		$result = $this->browser->get($yqlquery);
		if(!$result->isOk()){
			throw new Exception($result->getgetReasonPhrase(),$result->getStatusCode());
		}
		$content = $result->getContent();

		if ($this->cache != false) {
			$this->cache->put($key, $content, 60);
		}

		return json_decode($content);
	}
}