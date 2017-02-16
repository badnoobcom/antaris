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

class AJP13CustomResponseHeader implements IAJP13ResponseHeader
{
	/** @var string */
	private $strHeaderName;
	/** @var string */
	private $strHeaderValue;


	public function __construct(string $strHeaderName, string $strHeaderValue)
	{
		$this->strHeaderName  = $strHeaderName;
		$this->strHeaderValue = $strHeaderValue;
	}


	public function __destruct()
	{
		unset(
			$this->strHeaderName,
			$this->strHeaderValue
		);
	}


	public function getHeaderName(): string
	{
		return $this->strHeaderName;
	}


	public function getHeaderValue(): string
	{
		return $this->strHeaderValue;
	}


	public function getType(): int
	{
		return AJP13Constants::RESP_CUSTOM;
	}
}