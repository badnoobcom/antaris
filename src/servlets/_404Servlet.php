<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace servlets;


use com\badnoob\antaris\core\servlet\IServlet;
use com\badnoob\antaris\core\servlet\Servlet;
use com\badnoob\antaris\core\servlet\ServletConstants;

class _404Servlet implements IServlet
{
	use Servlet;

	public function run()
	{
		$this->objHttpResponse->setBody($this->createBody());
	}


	public function getRoute():string
	{
		return ServletConstants::RESPONSE_404_ROUTE;
	}


	private function createBody():string
	{
	    $strResourcesDir = __DIR__ . '/../webapps/resources';
	    $strTemplatePath = '/html/template.html';

		$strTemplate = file_get_contents($strResourcesDir.$strTemplatePath);
		$strContent = file_get_contents($strResourcesDir .'/html/404.content.html');

		if ($strTemplate === false) {
		    throw new ServletException('file template.html could not be found in resources directory '. $strTemplatePath);
        }

        $strBody = str_replace('[MAINCONTENT]', $strContent, $strTemplate);
		return $strBody;
	}
}