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


use com\badnoob\antaris\core\datatypes\StringWrapper;

class HttpResponse
{

	/**
	 * @var string[]
	 */
	private $arrCookies;

	/**
	 * @var string[]
	 */
	private $arrHeaders;

	/**
	 * @var	int
	 */
	private $intResponseCode;

	/**
	 * @var	StringWrapper
	 */
	private $objBody;


	public function __construct()
	{
		$this->arrCookies		= [];
		$this->arrHeaders		= [];
		$this->intResponseCode	= HttpResponseCodes::CODE_200_OK;
		$this->objBody			= new StringWrapper('');
	}


	public function __destruct()
	{
		unset(
			$this->arrCookies,
			$this->arrHeaders,
			$this->objBody,
			$this->intResponseCode
		);
	}


	/**
	 * @param string $strKey
	 * @param string $strValue
	 */
	public function addCookie(string $strKey, string $strValue)
	{
		$this->arrCookies[$strKey] = $strValue;
	}


	/**
	 * @param string $strKey
	 * @param string $strValue
	 */
	public function addHeader(string $strKey, string $strValue)
	{
		$this->arrHeaders[$strKey] = $strValue;
	}


	/**
	 * @param string $strValue
	 */
	public function appendBody(string $strValue)
	{
		$this->objBody->appendString($strValue);
	}


	/**
	 * @return StringWrapper
	 */
	public function getBody():StringWrapper
	{
		return $this->objBody;
	}


	/**
	 * @return string[]
	 */
	public function getCookies():array
	{
		return $this->arrCookies;
	}


	/**
	 * @return string[]
	 */
	public function getHeaders():array
	{
		return $this->arrHeaders;
	}


	/**
	 * @return int
	 */
	public function getResponseCode():int
	{
		return $this->intResponseCode;
	}


	/**
	 * @param int $intValue
	 */
	public function setResponseCode(int $intValue)
	{
		$this->intResponseCode = $intValue;
	}


	/**
	 * @param string $strValue
	 */
	public function setBody(string $strValue)
	{
		$this->objBody->setValue($strValue);
	}
}