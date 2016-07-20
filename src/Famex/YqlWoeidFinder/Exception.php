<?php
namespace Famex\YqlWoeidFinder;


class Exception extends \Exception
{
	const NO_QUERY_RESULT = 'No query result';
	const NO_WOEID = 'No WoEID found';
}