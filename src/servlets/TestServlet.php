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
use com\badnoob\antaris\utils\debugging\DebugPrinter;

class TestServlet implements IServlet
{
	use Servlet;

	public function run()
	{
		$this->objHttpResponse->setBody($this->createBody());

		$strCookie = $this->objHttpRequest->getCookie();

		$this->objHttpResponse->addHeader('X-XSS-Protection', '1; mode=block');

		if(stripos($strCookie, 'antaris') !== false)
		{
			$arrSplit = explode('; ', $strCookie);
			DebugPrinter::debug('cookie split: '. print_r($arrSplit, true) . PHP_EOL);
		}
		else
		{
			DebugPrinter::trace('writing cookie'. PHP_EOL);
			$this->objHttpResponse->addCookie('antaris', 'meinWert');
		}
	}


	public function getRoute():string
	{
		return '/test';
	}


	private function createBody():string
	{
		$strName = $this->objHttpRequest->getQueryParameterValue('name');

		$strResourcesDir = __DIR__ . '/../webapps/resources';
	    $strTemplatePath = '/html/template.html';

		$strTemplate = file_get_contents($strResourcesDir.$strTemplatePath);
		$strContent = file_get_contents($strResourcesDir .'/html/getposttest_form.content.html');

		if ($strTemplate === false) {
		    throw new ServletException('file template.html could not be found in resources directory '. $strTemplatePath);
        }

        $strContent = str_replace('[NAME]', $strName, $strContent);
        $strBody = str_replace('[MAINCONTENT]', $strContent, $strTemplate);
		return $strBody;
	}
}