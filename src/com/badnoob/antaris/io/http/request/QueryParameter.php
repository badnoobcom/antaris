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


class QueryParameter
{
	/**
	 * @var  string
	 */
	private $strKey;

	/**
	 * @var string
	 */
	private $strValue;


	public function __construct(string $strKey, string $strValue)
	{
		$this->strKey   = $strKey;
		$this->strValue = $strValue;
	}


	public function getKey():string
	{
		return $this->strKey;
	}


	public function getValue():string
	{
		return $this->strValue;
	}
}