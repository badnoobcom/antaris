<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\core\servlet;

use com\badnoob\antaris\io\http\HttpRequest;
use com\badnoob\antaris\io\http\HttpResponse;

trait Servlet
{
	/**
	 * @var	HttpRequest
	 */
	protected $objHttpRequest;

	/**
	 * @var	HttpResponse
	 */
	protected $objHttpResponse;


	/**
	 * @return HttpResponse
	 */
	final public function getResponse():HttpResponse
	{
		return $this->objHttpResponse;
	}


	/**
	 * @param HttpRequest $objRequest
	 */
	final public function injectRequest(HttpRequest $objRequest)
	{
		$this->objHttpRequest = $objRequest;
	}


	/**
	 * @param HttpResponse $objResponse
	 */
	final public function injectResponse(HttpResponse $objResponse)
	{
		$this->objHttpResponse = $objResponse;
	}


	abstract public function run();


	/**
	 * @return string
	 */
	abstract public function getRoute():string;
}