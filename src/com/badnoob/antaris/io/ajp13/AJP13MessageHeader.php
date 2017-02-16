<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\ajp13;

use com\badnoob\antaris\io\streams\ByteStream;
use com\badnoob\antaris\utils\debugging\DebugPrinter;

/**
 * Class AJP13MessageHeader
 *
 * req_header_name :=
 *  sc_req_header_name | (string)  [see below for how this is parsed]
 *
 * sc_req_header_name := 0xA0xx (integer)
 *
 * req_header_value := (string)
 *
 *
 * Name				Code value	Code name
 * accept			0xA001		SC_REQ_ACCEPT
 * accept-charset	0xA002		SC_REQ_ACCEPT_CHARSET
 * accept-encoding	0xA003		SC_REQ_ACCEPT_ENCODING
 * accept-language	0xA004		SC_REQ_ACCEPT_LANGUAGE
 * authorization	0xA005		SC_REQ_AUTHORIZATION
 * connection		0xA006		SC_REQ_CONNECTION
 * content-type		0xA007		SC_REQ_CONTENT_TYPE
 * content-length	0xA008		SC_REQ_CONTENT_LENGTH
 * cookie			0xA009		SC_REQ_COOKIE
 * cookie2			0xA00A		SC_REQ_COOKIE2
 * host				0xA00B		SC_REQ_HOST
 * pragma			0xA00C		SC_REQ_PRAGMA
 * referer			0xA00D		SC_REQ_REFERER
 * user-agent		0xA00E		SC_REQ_USER_AGENT
 *
 *
 * @package com\badnoob\antaris\io\ajp13
 */
class AJP13MessageHeader
{
	/** @var integer */
	private $intHeaderType;

	/** @var ByteStream */
	private $objMessage;

	/** @var string */
	private $strHeaderName;

	/** @var string */
	private $strHeaderValue;


	public function __construct(ByteStream $objMessage)
	{
		$this->objMessage = $objMessage;

		$this->readHeaderName();
		$this->readHeaderValue();
	}


	private function readHeaderName()
	{
		/**
		 * if value <= 0xA000, this is handled as a regular string, otherwise it's one of the SC_REQ constants.
		 */
		$intTypeOrLength = $this->objMessage->readUnsignedShort();

		if($intTypeOrLength <= AJP13Constants::STRING_LENGTH_BOUNDARY)
		{
			DebugPrinter::trace('reading string header with length: ' . $intTypeOrLength . PHP_EOL);
			$this->objMessage->setPosition($this->objMessage->getPosition() - 2);
			$this->strHeaderName = $this->objMessage->readStringLengthEncodedNullTerminated();
			$this->intHeaderType = 0;

			return;
		}

		$this->intHeaderType = $intTypeOrLength;
		if(!array_key_exists($this->intHeaderType, AJP13Constants::$HEADER_NAME_MAP))
		{
			throw new \UnexpectedValueException(__METHOD__
												. ' Couldn\'t recognize header of type 0x'
												. dechex($this->intHeaderType)
												. ', expecting any value of '
												. print_r(AJP13Constants::$HEADER_NAME_MAP, true));
		}

		$this->strHeaderName = AJP13Constants::$HEADER_NAME_MAP[ $this->intHeaderType ];
	}


	private function readHeaderValue()
	{
		$this->strHeaderValue = $this->objMessage->readStringLengthEncodedNullTerminated();
	}


	public function __destruct()
	{
		unset(
			$this->intHeaderType,
			$this->objPrinter,
			$this->objMessage,
			$this->strHeaderName,
			$this->strHeaderValue
		);
	}


	/**
	 * @return string
	 */
	public function getHeaderName():string
	{
		return $this->strHeaderName;
	}


	/**
	 * @return string
	 */
	public function getHeaderValue():string
	{
		return $this->strHeaderValue;
	}


	/**
	 * @return int
	 */
	public function getType():int
	{
		return $this->intHeaderType;
	}
}