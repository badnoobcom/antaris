<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\http\request;


use com\badnoob\antaris\io\ajp13\AJP13Constants;
use com\badnoob\antaris\io\ajp13\AJP13Message;
use com\badnoob\antaris\io\http\HttpRequest;

class HttpRequestFactory
{
	public static function buildFromAJP13Message(AJP13Message $objAJPRequest):HttpRequest
	{
		$objHttpRequest = new HttpRequest($objAJPRequest->getRequestURI());
		$arrHeaders		= $objAJPRequest->getAllHeaders();

		for($i = $objAJPRequest->getNumberOfHeaders() - 1; $i >= 0; $i--)
		{
			$objCurrentHeader = $arrHeaders[$i];
			$objHttpRequest->addHeader(new HttpHeader($objCurrentHeader->getHeaderName(), $objCurrentHeader->getHeaderValue()));
		}

		$arrQueryParameters	= $objAJPRequest->getAllQueryParameters();
		foreach ($arrQueryParameters as $strKey => $strValue)
		{
			$objQueryParameter = new QueryParameter($strKey, $strValue);
			$objHttpRequest->addQueryParameter($objQueryParameter);
		}

		$objHttpRequest->setPOSTData($objAJPRequest->getData());

		$objHttpRequest->setMethod(AJP13Constants::METHOD_TYPE_TO_METHOD_NAME[$objAJPRequest->getMethod()]);


		return $objHttpRequest;
	}
}