<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\ajp13\response;


use com\badnoob\antaris\io\ajp13\AJP13Constants;
use com\badnoob\antaris\io\http\HttpResponse;
use com\badnoob\antaris\io\http\HttpResponseCodes;
use com\badnoob\antaris\utils\debugging\DebugPrinter;
use com\badnoob\antaris\utils\objectpooling\AJP13Pool;

class AJP13ResponseFactory
{
	const EXCEPTION_MESSAGE = 'An exception was raised while processing the request.';
	const EXCEPTION_HTML_BODY = '<html><head><style>p{font-family:monospace}</style></head><body><h1>'.self::EXCEPTION_MESSAGE.'</h1>';
	const PHP_INFO_ON_EXCEPTION = false;

	/**
	 * Returns the binary string representation of the AJP13Response. It may include various chunked responses.
	 *
	 * @param HttpResponse $objHttpResponse
	 *
	 * @return string
	 */
	public static function prepareFromHttpResponse(HttpResponse $objHttpResponse):string
	{
		$objResponse	= AJP13Pool::getAJP13Response();
		$objBody		= $objHttpResponse->getBody();
		$intBodyLength	= $objBody->getLength();

		$arrCookies		= $objHttpResponse->getCookies();
		$arrHeaders		= $objHttpResponse->getHeaders();

		if(isset($arrCookies))
		{
			foreach($arrCookies as $strCookieKey => $strCookieValue)
			{
				$objResponse->addCookie($strCookieKey, $strCookieValue);
			}
		}

		self::addHeaders($arrHeaders, $objResponse);

		$objResponse->writeResponseHeaders($objHttpResponse->getResponseCode(), $intBodyLength);
		$strResponse = $objResponse->getAsString();
		$objResponse->recycle();

		$strBodyValue = $objBody->getValue();

		$strResponse .= self::writeBodyAndEnd($strBodyValue);

		AJP13Pool::freeAJP13Response($objResponse);

		return $strResponse;
	}


	/**
	 * Returns the binary string representation of the AJP13Response, which will only
	 * include the error message and the exception's message text. It may include
	 * various chunked responses.
	 *
	 * @param \Throwable $objException
	 *
	 * @return string
	 */
	public static function prepareFromException(\Throwable $objException):string
	{
		$objResponse	= AJP13Pool::getAJP13Response();
		$strBody		= self::EXCEPTION_HTML_BODY;

		DebugPrinter::error(self::EXCEPTION_MESSAGE);
		while(!is_null($objException))
		{
			$strBody .=	'<p>'. nl2br($objException->getMessage(), true) .'</p>
				<p>'. nl2br($objException->getTraceAsString(), true) .'</p>';
			DebugPrinter::error($objException->getMessage());
			DebugPrinter::error($objException->getTraceAsString());
			$objException = $objException->getPrevious();
		}

		if(self::PHP_INFO_ON_EXCEPTION)
		{
			$strBody .= '<p id=#php_info>';
			ob_start();
			phpinfo(-1);
			$strBody .= ob_get_clean() . '</p>';
			ob_end_clean();
		}

		$strBody .= '</body></html>';

		$intBodyLength	= strlen($strBody);

		$objResponse->writeResponseHeaders(HttpResponseCodes::CODE_503_INTERNAL_SERVER_ERROR, $intBodyLength);
		$strResponse = $objResponse->getAsString();
		$objResponse->recycle();

		$strResponse .= self::writeBodyAndEnd($strBody);

		AJP13Pool::freeAJP13Response($objResponse);

		return $strResponse;
	}

	/**
	 * @param int $intLength
	 * @return string
	 */
	public static function requestAdditionalBodyData(int $intLength):string
	{
		$objAJP13Response = AJP13Pool::getAJP13Response();
		$intLength = min($intLength, AJP13Constants::MAX_READ_SIZE);
		$objAJP13Response->writeGetBodyChunk($intLength);
		$strResponse = $objAJP13Response->getAsString();
		AJP13Pool::freeAJP13Response($objAJP13Response);
		DebugPrinter::debug('GETBODYCHUNK: '. $intLength);

		return $strResponse;
	}


	private static function writeBodyAndEnd(string $strBody):string
	{
		$strResponse = self::chunkBody($strBody);

		$objResponse = AJP13Pool::getAJP13Response();
		$objResponse->writeEndResponse();
		$strResponse .= $objResponse->getAsString();
		$objResponse->recycle();

		AJP13Pool::freeAJP13Response($objResponse);

		return $strResponse;
	}


	private static function chunkBody(string $strBody):string
	{
		$strResponse = '';
		$objResponse = AJP13Pool::getAJP13Response();
		$arrChunked = str_split($strBody, AJP13Constants::MAX_SEND_SIZE);
		foreach ($arrChunked as $strChunk)
		{
			$objResponse->writeBodyChunk($strChunk);
			$strResponse .= $objResponse->getAsString();
			$objResponse->recycle();
		}

		AJP13Pool::freeAJP13Response($objResponse);

		return $strResponse;
	}


	/**
	 * @param array $arrHeaders
	 * @param AJP13Response $objResponse
	 * @return string
	 */
	private static function addHeaders(array $arrHeaders, AJP13Response $objResponse):string
	{
		$strHeaders = '';
		if(!isset($arrHeaders))
		{
			return $strHeaders;
		}

		foreach ($arrHeaders as $strHeaderKey => $strHeaderValue)
		{
			$intHeaderKey = array_search(ucwords($strHeaderKey,'-'), AJP13Constants::RESP_HEADER_NAME_MAP, true);
			if($intHeaderKey !== false)
			{
				$objResponse->addStandardHeader($intHeaderKey, $strHeaderValue);
			}
			else
			{
				$objResponse->addCustomHeader($strHeaderKey, $strHeaderValue);
			}
		}

		return $strHeaders;
	}
}