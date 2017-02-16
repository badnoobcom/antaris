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


use servlets\_404Servlet;
use servlets\_IndexServlet;
use servlets\ConsoleServlet;
use servlets\FileUploadServlet;
use servlets\ImageToHtmlServlet;
use servlets\PHPInfoServlet;
use servlets\PostTestServlet;
use servlets\TestServlet;

class ServletRegistry
{
	/**
	 * @var IServlet[]
	 */
	private $arrServlets;


	public function __construct()
	{
		$this->arrServlets = [];
	}


	public function updateServlets()
	{
		/**
		 * yes, this is static at the moment and also not fail safe in any way. since this is only a prototype project,
		 * I didn't put in the effort
		 */
		include 'servlets' . DIRECTORY_SEPARATOR . '_404Servlet.php';
		include 'servlets' . DIRECTORY_SEPARATOR . 'TestServlet.php';
		include 'servlets' . DIRECTORY_SEPARATOR . 'PostTestServlet.php';
		include 'servlets' . DIRECTORY_SEPARATOR . 'FileUploadServlet.php';
		include 'servlets' . DIRECTORY_SEPARATOR . '_IndexServlet.php';
		include 'servlets' . DIRECTORY_SEPARATOR . 'ImageToHtmlServlet.php';
		include 'servlets' . DIRECTORY_SEPARATOR . 'PHPInfoServlet.php';

		$arrServlets = [
			new _404Servlet(),
			new TestServlet(),
			new PostTestServlet(),
			new FileUploadServlet(),
			new _IndexServlet(),
			new ImageToHtmlServlet(),
			new PHPInfoServlet(),
		];

		/**
		 * @var Servlet $objServlet
		 */
		foreach($arrServlets as $objServlet)
		{
			$this->arrServlets[ $objServlet->getRoute() ] = $objServlet;
		}
	}


	/**
	 * @param $strRoute
	 *
	 * @return IServlet
	 */
	public function getServletByRoute(string $strRoute): IServlet
	{
		return $this->arrServlets[ $strRoute ] ?? $this->arrServlets[ ServletConstants::RESPONSE_404_ROUTE ];
	}
}