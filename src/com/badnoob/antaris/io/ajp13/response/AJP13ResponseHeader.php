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

/**
 * Class AJP13ResponseHeader
 *
 * AJP13_SEND_HEADERS :=
 *   prefix_code       4
 *   http_status_code  (integer)
 *   http_status_msg   (string)
 *   num_headers       (integer)
 *   response_headers *(res_header_name header_value)
 *
 * res_header_name :=
 *   sc_res_header_name | (string)   [see below for how this is parsed]
 *
 * sc_res_header_name := 0xA0 (byte)
 *
 * header_value := (string)
 *
 * Name				Code value
 * Content-Type		0xA001
 * Content-Language	0xA002
 * Content-Length	0xA003
 * Date				0xA004
 * Last-Modified	0xA005
 * Location			0xA006
 * Set-Cookie		0xA007
 * Set-Cookie2		0xA008
 * Servlet-Engine	0xA009
 * Status			0xA00A
 * WWW-Authenticate	0xA00B
 *
 * @package com\badnoob\antaris\io\ajp13\response
 */
class AJP13ResponseHeader implements IAJP13ResponseHeader
{
	/**
	 * @var int
	 */
	private $intType;

	/**
	 * @var string
	 */
	private $strValue;


	public function __construct(int $intType, string $strValue)
	{
		if(!array_key_exists($intType, AJP13Constants::RESP_HEADER_NAME_MAP))
		{
			print_r(AJP13Constants::RESP_HEADER_NAME_MAP);
			throw new \InvalidArgumentException('Header of type 0x'
												. dechex($intType)
												. ' does not exist. Cannot create header.');
		}

		$this->intType  = $intType;
		$this->strValue = $strValue;
	}


	public function __destruct()
	{
		unset(
			$this->intType,
			$this->strValue
		);
	}


	/**
	 * @return int
	 */
	public function getType():int
	{
		return $this->intType;
	}


	/**
	 * @return string
	 */
	public function getHeaderName():string
	{
		return AJP13Constants::RESP_HEADER_NAME_MAP[ $this->intType ];
	}


	/**
	 * @return string
	 */
	public function getHeaderValue():string
	{
		return $this->strValue;
	}
}