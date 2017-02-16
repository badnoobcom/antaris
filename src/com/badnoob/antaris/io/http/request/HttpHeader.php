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


class HttpHeader
{
	const CONTENT_TYPE                    = 'content-type';
	const CONTENT_TYPE_MULTIPART_FORMDATA = 'multipart/form-data';
	const CONTENT_BOUNDARY                = 'boundary=';
	const SPLIT                           = "\r\n";

	/**
	 * @var string
	 */
	private $strName;

	/**
	 * @var string;
	 */
	private $strValue;


	public function __construct(string $strName, string $strValue)
	{
		$this->strName  = $strName;
		$this->strValue = $strValue;
	}


	public function getName():string{ return $this->strName; }


	public function getValue():string{ return $this->strValue; }
}