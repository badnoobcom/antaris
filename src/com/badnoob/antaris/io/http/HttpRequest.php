<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\http;


use com\badnoob\antaris\exceptions\NullPointerException;
use com\badnoob\antaris\io\http\request\HttpConstants;
use com\badnoob\antaris\io\http\request\HttpFormDataElement;
use com\badnoob\antaris\io\http\request\HttpHeader;
use com\badnoob\antaris\io\http\request\MultipartFormDataParser;
use com\badnoob\antaris\io\http\request\QueryParameter;

class HttpRequest
{
	/**
	 * @var QueryParameter[]
	 */
	private $arrQueryParameters;

	/**
	 * @var HttpHeader[]
	 */
	private $arrHeaders;

	/**
	 * @var HttpFormDataElement[]
	 */
	private $arrMultipartElements;

	/**
	 * @var string
	 */
	private $strMethod;

	/**
	 * @var string
	 */
	private $strRequestURI;


	/**
	 * @var string
	 */
	private $strPOSTData;


	public function __construct(string $strRequestURI)
	{
		$this->arrHeaders         = [];
		$this->arrQueryParameters = [];
		$this->strPOSTData        = '';
		$this->strRequestURI      = $strRequestURI;
	}


	public function addHeader(HttpHeader $objHttpHeader)
	{
		$this->arrHeaders[] = $objHttpHeader;
	}


	public function addQueryParameter(QueryParameter $objQueryParameter)
	{
		$this->arrQueryParameters[] = $objQueryParameter;
	}


	public function getCookie(): string
	{
		foreach($this->arrHeaders as $objCurrentHeader)
		{
			if(strtolower($objCurrentHeader->getName()) === HttpConstants::HEADER_NAME_COOKIE)
			{
				return $objCurrentHeader->getValue();
			}
		}

		return '';
	}


	public function getHeaders(): array{ return $this->arrHeaders; }


	/**
	 * @param string $strHeader
	 *
	 * @return \com\badnoob\antaris\io\http\request\HttpHeader
	 * @throws \com\badnoob\antaris\exceptions\NullPointerException
	 */
	public function getHeaderByName(string $strHeader): HttpHeader
	{
		foreach($this->arrHeaders as $objHeader)
		{
			if($objHeader->getName() === $strHeader) return $objHeader;
		}

		throw new NullPointerException('Header with name "' . $strHeader . '" not found.');
	}


	public function getMethod(): string{ return $this->strMethod; }


	public function setMethod(string $strMethod){ $this->strMethod = $strMethod; }


	public function getQueryParameters(): array{ return $this->arrQueryParameters; }


	public function getQueryParameterValue(string $strKey): string
	{
		foreach($this->arrQueryParameters as $objCurrentParameter)
		{
			if($objCurrentParameter->getKey() === $strKey) return $objCurrentParameter->getValue();
		}

		return '';
	}


	public function getPOSTData(): string{ return $this->strPOSTData; }


	public function setPOSTData(string $strData){ return $this->strPOSTData = $strData; }


	public function getRequestURI(): string{ return $this->strRequestURI; }


	/**
	 * @return HttpFormDataElement[]
	 * @throws \HttpRequestException
	 */
	public function getMultipartElementsFromPOST(): array
	{
		if($this->arrMultipartElements !== null)
		{
			return $this->arrMultipartElements;
		}

		try
		{
			$objContentHeader = $this->getHeaderByName(HttpHeader::CONTENT_TYPE);
		}
		catch(NullPointerException $e)
		{
			throw new \HttpRequestException('Could not read content from request.');
		}

		$objParser = new MultipartFormDataParser();

		$this->arrMultipartElements = $objParser->parseRequestBody($objContentHeader, $this->strPOSTData);

		return $this->arrMultipartElements;
	}


	/**
	 * @param $label
	 *
	 * @return \com\badnoob\antaris\io\http\request\HttpFormDataElement
	 */
	public function getMultipartElementByLabel($label): HttpFormDataElement
	{
		if($this->arrMultipartElements === null)
		{
			$this->getMultipartElementsFromPOST();
		}

		foreach($this->arrMultipartElements as $objElement)
		{
			if($objElement->getLabel() === $label) return $objElement;
		}

		return null;
	}
}