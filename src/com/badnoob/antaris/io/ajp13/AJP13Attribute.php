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
use com\badnoob\antaris\utils\debugging\DebugLevel;
use com\badnoob\antaris\utils\debugging\DebugPrinter;

/**
 * Class AJP13Attribute
 *
 *
 * attribute_name := sc_a_name | (sc_a_req_attribute string)
 *
 * attribute_value := (string)
 *
 * The attributes prefixed with a ? (e.g. ?context) are all optional. For each, there is a single byte code to indicate
 * the type of attribute, and then a string to give its value. They can be sent in any order (though the C code always
 * sends them in the order listed below). A special terminating code is sent to signal the end of the list of optional
 * attributes. The list of byte codes is
 * Information		Code Value	Note
 * ?context			0x01		Not currently implemented
 * ?servlet_path	0x02		Not currently implemented
 * ?remote_user		0x03
 * ?auth_type		0x04
 * ?query_string	0x05
 * ?route			0x06
 * ?ssl_cert		0x07
 * ?ssl_cipher		0x08
 * ?ssl_session		0x09
 * ?req_attribute	0x0A		Name (the name of the attribut follows)
 * ?ssl_key_size	0x0B
 * ?secret			0x0C
 * ?stored_method	0x0D
 * are_done			0xFF		request_terminator
 *
 *
 * @package com\badnoob\antaris\io\ajp13
 */
class AJP13Attribute
{
	const CONTEXT		= 0x01;
	const SERVLET_PATH	= 0x02;
	const REMOTE_USER	= 0x03;
	const AUTH_TYPE		= 0x04;
	const QUERY_STRING	= 0x05;
	const ROUTE			= 0x06;
	const SSL_CERT		= 0x07;
	const SSL_CIPHER		= 0x08;
	const SSL_SESSION	= 0x09;
	const REQ_ATTRIBUTE	= 0x0A;
	const SSL_KEY_SIZE	= 0x0B;
	const SECRET			= 0x0C;
	const STORED_METHOD	= 0x0D;
	const ARE_DONE		= 0xFF;

	private $intAttributeType;
	private $strAttributeName;
	private $strValue;

	public function __construct(ByteStream $objMessage)
	{
		$this->intAttributeType	= $objMessage->readUnsignedByte();

		DebugPrinter::trace('read attribute type: 0x'. strtoupper(dechex($this->intAttributeType)));

		/**
		 * Read attribute name, if necessary.
		 */
		if($this->intAttributeType === self::REQ_ATTRIBUTE)
		{
			$this->strAttributeName = $objMessage->readStringLengthEncodedNullTerminated();
		}

		DebugPrinter::trace('Found AJP13Attribute '. $this->strAttributeName ?? '' .'.' );

		/**
		 * All attributes, except ARE_DONE, will have a payload following.
		 */
		$this->strValue = '';
		if($this->intAttributeType !== self::ARE_DONE)
		{
			$this->strValue = $objMessage->readStringLengthEncodedNullTerminated();
		}

		if(DebugPrinter::getLevel() & DebugLevel::TRACE)
		{
			DebugPrinter::printKeyValue('0x' . dechex($this->intAttributeType), $this->strValue);
		}
	}


	/**
	 * @return string
	 */
	public function getName():string
	{
		return $this->strAttributeName ?? '';
	}


	/**
	 * @return int
	 */
	public function getType():int
	{
		return $this->intAttributeType;
	}


	/**
	 * @return string
	 */
	public function getValue():string
	{
		return $this->strValue;
	}
}