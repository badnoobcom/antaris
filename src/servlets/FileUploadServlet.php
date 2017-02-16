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
use com\badnoob\antaris\io\http\request\HttpConstants;
use com\badnoob\antaris\utils\debugging\DebugPrinter;

class FileUploadServlet implements IServlet
{
    use Servlet;


    public function getRoute():string
    {
        return '/fileupload';
    }


    public function run()
    {
        $this->objHttpResponse->setBody($this->createBody());
    }


    /**
     * @return string
     */
    private function createBody():string
    {
        $strBasePath = __DIR__.DIRECTORY_SEPARATOR.'../webapps/resources/html/';
        if($this->objHttpRequest->getMethod() === HttpConstants::METHOD_NAME_GET)
        {
            $strPath = $strBasePath.'fileupload_form.content.html';
            $strResponse = file_get_contents($strPath);
        }
        else
        {
            $strPath = $strBasePath.'fileupload_result.content.html';
            $strResponse     = file_get_contents($strPath);
            $strData         = $this->objHttpRequest->getPOSTData();
            $arrPlaceholders = [
                    '[XX]',
                    '[HEADERS]',
                    '[FORM-PARTS]',
                    '[CONTENT]'
            ];

            $strFileContents = $this->objHttpRequest->getMultipartElementsFromPOST();

            $strHeaders = print_r($this->objHttpRequest->getHeaders(), true);
            $strHeaders = preg_replace('/ /m', '&nbsp;', $strHeaders);
            $strHeaders = nl2br($strHeaders);

            $arrReplacements = [
                    strlen($strData),
                    $strHeaders,
                    $this->toHtml($strFileContents),
                    $this->toHtml(DebugPrinter::getStringAsHexDump($strFileContents[0]->getBody()))
            ];
            $strResponse     = str_replace($arrPlaceholders, $arrReplacements, $strResponse);
        }

        $strResourcesDir = __DIR__ . '/../webapps/resources';
	    $strTemplatePath = '/html/template.html';

		$strTemplate = file_get_contents($strResourcesDir.$strTemplatePath);

		if ($strTemplate === false) {
		    throw new ServletException('file template.html could not be found in resources directory '. $strTemplatePath);
        }

        $strBody = str_replace('[MAINCONTENT]', $strResponse, $strTemplate);
		return $strBody;
    }


    private function toHtml($strValue):string
    {
        return nl2br(htmlentities($strValue));
    }
}