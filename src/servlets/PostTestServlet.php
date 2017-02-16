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

class PostTestServlet implements IServlet
{
	use Servlet;

	public function run()
	{
		$this->objHttpResponse->setBody($this->createBody());
	}


	public function getRoute():string
	{
		return '/postMe';
	}


	private function createBody():string
	{
		$strPostValue = $this->objHttpRequest->getPOSTData();

		$strResourcesDir = __DIR__ . '/../webapps/resources';
	    $strTemplatePath = '/html/template.html';

		$strTemplate = file_get_contents($strResourcesDir.$strTemplatePath);
		$strContent = file_get_contents($strResourcesDir .'/html/getposttest_result.content.html');
		$strContent = str_replace('[POSTDATA]',
                                  nl2br(htmlentities(DebugPrinter::getStringAsHexDump($strPostValue))),
                                  $strContent);

		if ($strTemplate === false) {
		    throw new ServletException('file template.html could not be found in resources directory '. $strTemplatePath);
        }

        $strBody = str_replace('[MAINCONTENT]', $strContent, $strTemplate);
		return $strBody;
	}


	private function getAndSetCookie()
	{
		$strCookie = $this->objHttpRequest->getCookie();

		DebugPrinter::debug('running PostTestServlet. got cookie:' . $strCookie);
		if(stripos($strCookie, 'antaris') !== false)
		{
			$arrSplit = explode('; ', $strCookie);
			DebugPrinter::debug('cookie split: ' . print_r($arrSplit, true));
		}
		else
		{
			DebugPrinter::debug('writing cookie');
			$this->objHttpResponse->addCookie('antaris', 'meinWert');
		}
	}
}