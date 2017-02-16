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

interface IServlet
{
	/**
	 * @return HttpResponse
	 */
	public function getResponse():HttpResponse;


	/**
	 * @param HttpRequest $objRequest
	 */
	public function injectRequest(HttpRequest $objRequest);


	/**
	 * @param HttpResponse $objResponse
	 */
	public function injectResponse(HttpResponse $objResponse);


	public function run();


	/**
	 * @return string
	 */
	public function getRoute():string;
}