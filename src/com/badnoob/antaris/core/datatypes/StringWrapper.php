<?php

/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\core\datatypes;

class StringWrapper
{
	/**
	 * @var string
	 */
	private $strValue;

	/**
	 * @var int
	 */
	private $intLength;


	/**
	 * StringWrapper constructor.
	 * @param string $strValue
	 */
	public function __construct(string $strValue)
	{
		$this->strValue = $strValue;

		$this->updateLength();
	}


	public function __destruct()
	{
		unset($this->intLength);
		unset($this->strValue);
	}


	/**
	 * @param StringWrapper $objValue
	 */
	public function append($objValue)
	{
		$this->setValue($this->strValue . $objValue->getValue());
	}


	/**
	 * @param string $strValue
	 */
	public function appendString(string $strValue)
	{
		$this->setValue($this->strValue . $strValue);
	}


	/**
	 * @param string $strNeedle
	 * @return bool
	 */
	public function contains(string $strNeedle):bool
	{
		return strpos($this->strValue, $strNeedle) !== false;
	}


	/**
	 * @param $strNeedle
	 * @return bool
	 */
	public function startsWith(string $strNeedle):bool
	{
		return strpos($this->strValue, $strNeedle) === 0;
	}


	/**
	 * @return string
	 */
	public function toLowerCase():string
	{
		return strtolower($this->strValue);
	}


	private function updateLength()
	{
		if ($this->strValue === null)
		{
			$this->intLength = 0;
		}

		$this->intLength = strlen($this->strValue);
	}


	/**
	 * @return int
	 */
	public function getLength():int
	{
		return $this->intLength;
	}


	/**
	 * @return string
	 */
	public function getValue():string
	{
		return $this->strValue;
	}

	/**
	 * @param string $strValue
	 */
	public function setValue(string $strValue)
	{
		$this->strValue = $strValue;

		$this->updateLength();
	}
}