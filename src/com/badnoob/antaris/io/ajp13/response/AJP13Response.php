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
use com\badnoob\antaris\io\streams\ByteStream;
use com\badnoob\antaris\utils\debugging\DebugPrinter;
use com\badnoob\antaris\utils\objectpooling\ByteStreamPool;

/**
 * Class AJP13Response
 *
 *
 * Packet Format (Server->Container)
 * Byte     0       1        2       3       4...(n+3)
 * Contents 0x12    0x34     Data Length (n) Data
 * Packet Format (Container->Server)
 * Byte     0       1        2       3       4...(n+3)
 * Contents A       B        Data Length (n) Data
 *
 *
 * Code Type of Packet      Meaning
 * 3	    Send Body Chunk     Send a chunk of the body from the servlet container to the web server (and presumably, onto the browser).
 * 4	    Send Headers        Send the response headers from the servlet container to the web server (and presumably, onto the browser).
 * 5	    End Response        Marks the end of the response (and thus the request-handling cycle).
 * 6	    Get Body Chunk      Get further data from the request if it hasn't all been transferred yet.
 * 9	    CPong Reply         The reply to a CPing request
 *
 *
 *
 * Response Packet Structure
 * For messages which the container can send back to the server.
 *
 * AJP13_SEND_BODY_CHUNK :=
 *   prefix_code   3
 *   chunk_length  (integer)
 *   chunk        *(byte)
 *
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
 * AJP13_END_RESPONSE :=
 *   prefix_code       5
 *   reuse             (boolean)
 *
 *
 * AJP13_GET_BODY_CHUNK :=
 *   prefix_code       6
 *   requested_length  (integer)
 *
 *
 * Details:
 *
 * Send Body Chunk
 * The chunk is basically binary data, and is sent directly back to the browser.
 *
 * Send Headers
 * The status code and message are the usual HTTP things (e.g. "200" and "OK"). The response header names are encoded the same way the request header names are. See above for details about how the the codes are distinguished from the strings. The codes for common headers are:
 *
 * Name				Code value
 * Content-Type		0xA001
 * Content-Language	0xA002
 * Content-Length	0xA003
 * Date				0xA004
 * Last-Modified		0xA005
 * Location			0xA006
 * Set-Cookie		0xA007
 * Set-Cookie2		0xA008
 * Servlet-Engine	0xA009
 * Status			0xA00A
 * WWW-Authenticate	0xA00B
 * After the code or the string header name, the header value is immediately encoded.
 *
 * End Response
 * Signals the end of this request-handling cycle. If the reuse flag is true (==1), this TCP connection can now be used to handle new incoming requests. If reuse is false (anything other than 1 in the actual C code), the connection should be closed.
 *
 * Get Body Chunk
 * The container asks for more data from the request (If the body was too large to fit in the first packet sent over or when the request is chuncked). The server will send a body packet back with an amount of data which is the minimum of the request_length, the maximum send body size (8186 (8 Kbytes - 6)), and the number of bytes actually left to send from the request body.
 * If there is no more data in the body (i.e. the servlet container is trying to read past the end of the body), the server will send back an "empty" packet, which is a body packet with a payload length of 0. (0x12,0x34,0x00,0x00)
 *
 *
 * @package com\badnoob\antaris\io\ajp13\response
 */
class AJP13Response
{
	/**
	 * @var AJP13ResponseHeader[];
	 */
	private $arrHeaders;

	/**
	 * @var bool
	 */
	private $blnReuseConnectionFlag;

	/**
	 * @var \com\badnoob\antaris\io\streams\ByteStream
	 */
	private $objMessage;


	public function __construct(bool $blnReuseConnection = true)
	{
		$this->arrHeaders = [];
		$this->blnReuseConnectionFlag = $blnReuseConnection;
		$this->objMessage = ByteStreamPool::getByteStream();
		$this->reserveHeaderBytes();
	}


	public function __destruct()
	{
		ByteStreamPool::freeByteStream($this->objMessage);

		/**
		 * @var IAJP13ResponseHeader
		 */
		foreach($this->arrHeaders as $objHeader)
		{
			unset($objHeader);
		}

		unset(
			$this->arrHeaders,
			$this->blnReuseConnectionFlag,
			$this->objMessage
		);
	}


	/**
	 * @param string $strName
	 * @param string $strValue
	 */
	public function addCookie(string $strName, string $strValue)
	{
		$this->addStandardHeader(AJP13Constants::SC_RESP_SET_COOKIE, $strName .'='. $strValue);
	}


	public function addCustomHeader(string $strHeaderName, string $strHeaderValue)
	{
		$this->arrHeaders[] = new AJP13CustomResponseHeader($strHeaderName, $strHeaderValue);
	}


	/**
	 * @param int $intType
	 * @param string $strValue
	 */
	public function addStandardHeader(int $intType, string $strValue)
	{
		$this->arrHeaders[] = new AJP13ResponseHeader($intType, $strValue);
	}


	public function recycle()
	{
		$this->objMessage->reset();
		$this->reserveHeaderBytes();
	}


	/**
	 * @param string $strValue
	 */
	public function writeBodyChunk(string $strValue)
	{
		DebugPrinter::trace('write body chunk at position ['. $this->objMessage->getPosition() .']');
		$this->objMessage->writeUnsignedByte(AJP13Constants::JK_AJP13_SEND_BODY_CHUNK);
		$intLen = strlen($strValue);
		$this->objMessage->writeUnsignedShort($intLen);
		$this->objMessage->writeString($strValue);

		$this->writeMessageHeaderAndLength();
	}


	public function writeEndResponse()
	{
		$this->objMessage->writeUnsignedByte(AJP13Constants::JK_AJP13_END_RESPONSE);
		// write "reuse" flag as boolean int (0/1)
		$this->objMessage->writeUnsignedByte((int) $this->blnReuseConnectionFlag);

		$this->writeMessageHeaderAndLength();
	}


	/**
	 * @param int $intLengthToRequest
	 */
	public function writeGetBodyChunk(int $intLengthToRequest)
	{
		$this->objMessage->writeUnsignedByte(AJP13Constants::JK_AJP13_GET_BODY_CHUNK);
		$this->objMessage->writeUnsignedShort($intLengthToRequest);

		$this->writeMessageHeaderAndLength();
	}


	/**
	 * @param int $intStatusCode
	 * @param int $intDataLength
	 */
	public function writeResponseHeaders(int $intStatusCode, int $intDataLength)
	{
		//prefix code
		$this->objMessage->writeUnsignedByte(AJP13Constants::JK_AJP13_SEND_HEADERS);
		$this->objMessage->writeUnsignedShort($intStatusCode);
		//todo: map status code to status message
		$this->objMessage->writeStringLengthEncodedNullTerminated('OK');

		$numHeaders = count($this->arrHeaders);

		//numheaders
		$numHeaders += 2;
		$this->objMessage->writeUnsignedShort($numHeaders);
		$this->writeContentType();
		$this->writeContentLength($intDataLength);


		/** @var AJP13ResponseHeader $objHeader */
		foreach($this->arrHeaders as $objHeader)
		{
			$this->writeResponseHeader($objHeader);
		}

		$this->writeMessageHeaderAndLength();
	}


	private function reserveHeaderBytes()
	{
		/**
		 * Reserve 4 bytes for "AB" message start, followed by length (will be set afterwards)
		 */
		$this->objMessage->setPosition(0);
		$this->objMessage->writeUnsignedShort(0x4142); //signature AB
		$this->objMessage->writeUnsignedShort(0);
	}


	private function writeMessageHeaderAndLength()
	{
		$this->objMessage->setPosition(2);
		$intLength = $this->objMessage->getLength() - 4; //initial 4 bytes don't count as length
		$this->objMessage->writeUnsignedShort($intLength); //2 bytes length
	}


	/**
	 * @return \com\badnoob\antaris\io\streams\ByteStream
	 */
	public function getStream():ByteStream
	{
		return $this->objMessage;
	}


	/**
	 * @return string
	 */
	public function getAsString():string
	{
		$intTMPPos	= $this->objMessage->getPosition();
		$this->objMessage->setPosition(0);
		$strResult	= $this->objMessage->readAll();
		$this->objMessage->setPosition($intTMPPos);

		$intMessageLength = $this->objMessage->getLength();
		$intStringLength = strlen($strResult);

		DebugPrinter::trace('readAsString streamLength: '. $intMessageLength .', stringLength: '. $intStringLength . PHP_EOL);

		return $strResult;
	}


	private function writeContentType()
	{
		//content-type
		$this->objMessage->writeUnsignedShort(AJP13Constants::SC_RESP_CONTENT_TYPE);
		$this->objMessage->writeStringLengthEncodedNullTerminated('text/html; charset=utf-8');
	}


	/**
	 * @param int $intDataLength
	 */
	private function writeContentLength(int $intDataLength)
	{
		//content-length
		$this->objMessage->writeUnsignedShort(AJP13Constants::SC_RESP_CONTENT_LENGTH);
		$this->objMessage->writeStringLengthEncodedNullTerminated((string)$intDataLength);
	}


	/**
	 * @param IAJP13ResponseHeader $objHeader
	 */
	private function writeResponseHeader(IAJP13ResponseHeader $objHeader)
	{
		/**
		 * if it's a known and commonly used header, write the binary type
		 * otherwise write the header name as length encoded, null terminated string
		 */
		if(in_array($objHeader->getHeaderName(), AJP13Constants::RESP_HEADER_NAME_MAP, true))
		{
			$this->objMessage->writeUnsignedShort($objHeader->getType());
		}
		else
		{
			$this->objMessage->writeStringLengthEncodedNullTerminated($objHeader->getHeaderName());
		}

		$this->objMessage->writeStringLengthEncodedNullTerminated($objHeader->getHeaderValue());
	}
}