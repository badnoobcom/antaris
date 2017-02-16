<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * The representation of an AJP13 Message, including parsing and accessor methods.
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\ajp13;

use com\badnoob\antaris\io\socketserver\SocketServerWorker;
use com\badnoob\antaris\io\streams\ByteStream;
use com\badnoob\antaris\utils\debugging\DebugLevel;
use com\badnoob\antaris\utils\debugging\DebugPrinter;
use com\badnoob\antaris\utils\objectpooling\ByteStreamPool;

/*
 * The HTTP method, encoded as a single byte:
 *
 * Command Name		Code
 * OPTIONS			1
 * GET				2
 * HEAD				3
 * POST				4
 * PUT				5
 * DELETE			6
 * TRACE				7
 * PROPFIND			8
 * PROPPATCH			9
 * MKCOL				10
 * COPY				11
 * MOVE				12
 * LOCK				13
 * UNLOCK			14
 * ACL				15
 * REPORT			16
 * VERSION-CONTROL	17
 * CHECKIN			18
 * CHECKOUT			19
 * UNCHECKOUT		20
 * SEARCH			21
 * MKWORKSPACE		22
 * UPDATE			23
 * LABEL				24
 * MERGE				25
 * BASELINE_CONTROL	26
 * MKACTIVITY		27
 * Later version of ajp13, when used with mod_jk2, will transport additional methods, even if they are not in this list.
 *
 * ***********************************************************
 *
 * AJP13_FORWARD_REQUEST :=
 *	prefix_code      (byte) 0x02 = JK_AJP13_FORWARD_REQUEST
 *	method           (byte)
 *	protocol         (string)
 *	req_uri          (string)
 *	remote_addr      (string)
 *	remote_host      (string)
 *	server_name      (string)
 *	server_port      (integer)
 *	is_ssl           (boolean)
 *	num_headers      (integer)
 *					 request_headers *(req_header_name req_header_value)
 *	attributes      *(attribut_name attribute_value)
 *	request_terminator (byte) OxFF    <---- seems to be last attribute ARE_DONE
 *
 * Byte
 *	A single byte.
 * Boolean
 *	A single byte, 1 = true, 0 = false. Using other non-zero values as true (i.e. C-style) may work in some places,
 *  but it won't in others.
 * Integer
 *	A number in the range of 0 to 2^16 (32768). Stored in 2 bytes with the high-order byte first.
 * String
 *	A variable-sized string (length bounded by 2^16). Encoded with the length packed into two bytes first, followed
 *  by the string (including the terminating '\0'). Note that the encoded length does not include the trailing '\0'
 *  -- it is like strlen. This is a touch confusing on the Java side, which is littered with odd autoincrement
 *  statements to skip over these terminators. I believe the reason this was done was to allow the C code to be extra
 *  efficient when reading strings which the servlet container is sending back -- with the terminating \0 character,
 *  the C code can pass around references into a single buffer, without copying. If the \0 was missing, the C code
 *  would have to copy things out in order to get its notion of a string. Note a size of -1 (65535) indicates a null
 *  string and no data follow the length in this case.
 */
class AJP13Message
{
	const PACKET_TYPES					= [
		0x02, //Forward Request
		0x07, //Shutdown
		0x08, //Ping
		0x0A  //CPing
	];
	const HTTP_METHOD_OPTIONS				= 1;
	const HTTP_METHOD_GET					= 2;
	const HTTP_METHOD_HEAD					= 3;
	const HTTP_METHOD_POST					= 4;

	const MESSAGE_TYPE_FORWARD_REQUEST		= 2;
	const MESSAGE_SIGNATURE					= 0x1234;

	/** @var AJP13Attribute[] */
	private $arrAttributes;
	/** @var AJP13MessageHeader[] */
	private $arrHeaders;
	/** @var array */
	private $arrQueryStringKeyValuePairs;
	/** @var boolean */
	private $blnIsSSL;
	/** @var int */
	private $intLength;
	/** @var int */
	private $intMethod;
	/** @var int */
	private $intNumberOfHeaders;
	/** @var int */
	private $intPrefixType;
	/** @var int */
	private $intServerPort;
	/**
	 * @var int Points to the starting position in the stream
	 */
	private $intStreamOffset;
	/** @var string */
	private $strData;
	/** @var string */
	private $strProtocol;
	/** @var string */
	private $strRequestURI;
	/** @var string */
	private $strRemoteAddress;
	/** @var string */
	private $strRemoteHost;
	/** @var string */
	private $strServerName;
	/** @var \com\badnoob\antaris\io\streams\ByteStream */
	private $objStream;

	public function __construct(ByteStream $objMessage = null)
	{
		$this->reset();

		if(is_object($objMessage))
		{
			$this->setStream($objMessage);
		}
	}


	public function reset()
	{
		$this->arrAttributes	= [];
		$this->arrHeaders	= [];
		$this->intMethod		= 0;
		$this->intLength		= 0;
		$this->intPrefixType	= 0;
		$this->strData		= '';
		unset($this->objStream);
	}


	/**
	 * @param ByteStream $objMessage
	 */
	public function setStream(ByteStream $objMessage)
	{
		if(!is_object($objMessage)) return;

		$this->objStream = &$objMessage;
		$this->intStreamOffset = $this->objStream->getPosition();

		if(DebugPrinter::getLevel() & DebugLevel::TRACE)
		{
			$baMessage = ByteStreamPool::getByteStream();
			$baMessage->writeString($this->objStream->readAll());
			$baMessage->setPosition(0);
			DebugPrinter::trace(DebugPrinter::getStringAsHexDump($baMessage->readAll()));
		}

		$this->objStream->setPosition($this->intStreamOffset);
	}


	/**
	 * @param string $strData
	 *
	 * @return bool
	 */
	public function getIsContentFullyReceived(string $strData):bool
	{
		$intContentLength = $this->getContentLength();

		DebugPrinter::trace('isContentFullyReceived? strlen(data): '. strlen($strData) .', content-length:'.
							  $intContentLength);

		return $intContentLength <= 0
			   || $strData !== null
			   && strlen($strData) === $intContentLength;
	}


	/**
	 * @param int $intProcessingState
	 */
	private function readData(int $intProcessingState)
	{
		$this->intPrefixType = $this->objStream->readUnsignedByte();
		DebugPrinter::trace('prefix type: 0x'. dechex($this->intPrefixType) . PHP_EOL);

		/**
		 * Special handling for body messages. If the first byte is not 0xA0, the first "two" bytes represent the data
		 * length. Therefore, data cannot exceed 0x9999, what is bounded by the 8k max message size anyway. o.O
		 * And be fucking cautious, in case your BODY package length starts with one of the package types, e.g. 02 47
		 */
		if($intProcessingState === SocketServerWorker::WORKER_STATE_WAITING_FOR_BODY
		   || !in_array($this->intPrefixType, self::PACKET_TYPES, true))
		{
			/**
			 * So, let's take the prefix as the first byte of the 2-byte length field, shift it 8 bits left and append
			 * the second byte that would otherwise be used to read the method type. Tada, we have the correct length of
			 * the current data packet.
			 */
			$intPacketLength = $this->intPrefixType << 8 | $this->objStream->readUnsignedByte();
			DebugPrinter::debug('Reading BODY data. Length: '. $intPacketLength .', bytesAvailable: '.
									$this->objStream->getBytesAvailable());

			$intToRead = min($intPacketLength, $this->objStream->getBytesAvailable());
			DebugPrinter::debug('attempting to read ['. $intToRead .'] Bytes ...');

			$this->strData = $this->objStream->readString($intToRead);

			return;
		}

		$this->readProperties();

		$this->readHeaders();
		DebugPrinter::trace('bytesAvailable after headers: '. $this->objStream->getBytesAvailable() . PHP_EOL);

		$this->readAttributes();

		DebugPrinter::trace('bytesAvailable after attributes: '. $this->objStream->getBytesAvailable() . PHP_EOL);
		/*
		 * AJP13_FORWARD_REQUEST :=
    		prefix_code      (byte) 0x02 = JK_AJP13_FORWARD_REQUEST
			method           (byte)
			protocol         (string)
			req_uri          (string)
			remote_addr      (string)
			remote_host      (string)
			server_name      (string)
			server_port      (integer)
			is_ssl           (boolean)
			num_headers      (integer)
							 request_headers *(req_header_name req_header_value)
			attributes      *(attribut_name attribute_value)
			request_terminator (byte) OxFF
		*/
	}


	/**
	 * @param int $intProcessingState
	 *
	 * @return AJP13Message $this
	 */
	public function parseMessage(int $intProcessingState):AJP13Message
	{
		DebugPrinter::trace('stream length:'. $this->objStream->getLength() . PHP_EOL);

		if($this->objStream->getBytesAvailable() !== 0)
		{
			DebugPrinter::trace('message position: '. $this->objStream->getPosition() . PHP_EOL);
			if($this->intLength > 0)
			{
				$this->readData($intProcessingState);
			}
		}

		return $this;
	}


	/**
	 * @return AJP13Message $this
	 */
	public function readHeaderAndLength():AJP13Message
	{
		$this->objStream->setPosition($this->intStreamOffset);

		$intReadHead = $this->objStream->readByte() << 8 | $this->objStream->readByte();

		DebugPrinter::debug('header read: 0x'. dechex($intReadHead) . PHP_EOL);

		if($intReadHead !== self::MESSAGE_SIGNATURE)
		{
			DebugPrinter::warn('Incorrect head signature of "'. $intReadHead .'".'. PHP_EOL);
			return $this;
		}

		DebugPrinter::debug('Head signature read. Proceeding...'. PHP_EOL);

		$this->intLength = $this->objStream->readUnsignedShort();

		DebugPrinter::debug('Data length: '. $this->intLength . PHP_EOL);

		return $this;
	}


	/**
	 * @return AJP13Attribute[]
	 */
	public function getAttributes():array
	{
		return $this->arrAttributes;
	}


	/**
	 * @param $strName
	 *
	 * @return AJP13Attribute | null
	 */
	public function getAttributeByName(string $strName):AJP13Attribute
	{
		foreach($this->arrAttributes as $objCurrentAttribute)
		{
			if($objCurrentAttribute->getName() === $strName) return $objCurrentAttribute;
		}

		return null;
	}


	/**
	 * @param $intType
	 * @return AJP13MessageHeader | null
	 */
	public function getHeaderByType(int $intType):AJP13MessageHeader
	{
		/** @var AJP13MessageHeader */
		$objCurrentHeader = null;
		$intLen = count($this->arrHeaders);
		foreach($this->arrHeaders as $objCurrentHeader)
		{
			if($objCurrentHeader->getType() === $intType) return $objCurrentHeader;
		}

		return null;
	}


	/**
	 * @return string
	 */
	public function getCookie():string
	{
		/** @var AJP13MessageHeader*/
		$objCookieHeader = $this->getHeaderByType(AJP13Constants::SC_REQ_COOKIE);

		if(!$objCookieHeader) return null;

		return $objCookieHeader->getHeaderValue();
	}


	/**
	 * @return int
	 */
	public function getContentLength():int
	{
		$objHeaderContentLength = $this->getHeaderByType(AJP13Constants::SC_REQ_CONTENT_LENGTH);
		if($objHeaderContentLength !== null)
		{
			return (int) $objHeaderContentLength->getHeaderValue();
		}

		return 0;
	}


	/**
	 * @return string
	 */
	public function getServerName():string
	{
		return $this->strServerName;
	}


	/**
	 * @return bool
	 */
	public function getIsSSL():bool
	{
		return $this->blnIsSSL;
	}


	/**
	 * @return int
	 */
	public function getLength():int
	{
		return $this->intLength;
	}


	/**
	 * @return int
	 */
	public function getMethod():int
	{
		return $this->intMethod;
	}


	/**
	 * @return int
	 */
	public function getNumberOfHeaders():int
	{
		return $this->intNumberOfHeaders;
	}


	/**
	 * @return int
	 */
	public function getPrefixType():int
	{
		return $this->intPrefixType;
	}


	/**
	 * @return int
	 */
	public function getServerPort():int
	{
		return $this->intServerPort;
	}


	/**
	 * @return string
	 */
	public function getData():string
	{
		return $this->strData;
	}


	/**
	 * @return string
	 */
	public function getProtocol():string
	{
		return $this->strProtocol;
	}


	/**
	 * @return string
	 */
	public function getRequestURI():string
	{
		return $this->strRequestURI;
	}


	/**
	 * @return string
	 */
	public function getRemoteAddress():string
	{
		return $this->strRemoteAddress;
	}


	/**
	 * @return string
	 */
	public function getRemoteHost():string
	{
		return $this->strRemoteHost;
	}


	public function getStream():ByteStream
	{
		return $this->objStream;
	}


	public function getQueryStringValue(string $strKey):string
	{
		return $this->arrQueryStringKeyValuePairs[$strKey] ?? null;
	}


	/**
	 * @return AJP13MessageHeader[]
	 */
	public function getAllHeaders():array
	{
		return $this->arrHeaders;
	}


	/**
	 * @return array
	 */
	public function getAllQueryParameters():array
	{
		return $this->arrQueryStringKeyValuePairs ?: [];
	}


	private function parseQueryString(AJP13Attribute $objCurrentAttribute)
	{
		$strValue	= $objCurrentAttribute->getValue();
		$arrPairs	= explode('&', $strValue);
		$intLen		= count($arrPairs);
		if($intLen === 0) return;

		$this->arrQueryStringKeyValuePairs = [];

		foreach($arrPairs as $arrCurrentPair)
		{
			$arrKeyValue = explode('=', $arrCurrentPair);
			$strKey = $arrKeyValue[0];

			if(isset($this->arrQueryStringKeyValuePairs[$strKey])) continue;

			$this->arrQueryStringKeyValuePairs[$strKey] = null;

			if(count($arrKeyValue) !== 2) continue;

			$this->arrQueryStringKeyValuePairs[$strKey] = $arrKeyValue[1];
		}
	}


	private function readProperties()
	{
		$this->intMethod = $this->objStream->readUnsignedByte();
		if(DebugPrinter::getLevel() & DebugLevel::DEBUG)
		{
			DebugPrinter::printKeyValue('method type', $this->intMethod);
			DebugPrinter::printKeyValue('protocol', $this->strProtocol ?? '');
			DebugPrinter::debug('requestURI: ' . $this->strRequestURI . PHP_EOL);
			DebugPrinter::debug('remoteAddress: ' . $this->strRemoteAddress . PHP_EOL);
			DebugPrinter::debug('remoteHost: ' . $this->strRemoteHost . PHP_EOL);
			DebugPrinter::debug('serverName: ' . $this->strServerName . PHP_EOL);
			DebugPrinter::debug('serverPort: ' . $this->intServerPort . PHP_EOL);
			DebugPrinter::debug('isSSL: ' . (int) $this->blnIsSSL . PHP_EOL);
		}

		$this->strProtocol = $this->objStream->readStringLengthEncodedNullTerminated();
		$this->strRequestURI = $this->objStream->readStringLengthEncodedNullTerminated();
		$this->strRemoteAddress = $this->objStream->readStringLengthEncodedNullTerminated();
		$this->strRemoteHost = $this->objStream->readStringLengthEncodedNullTerminated();
		$this->strServerName = $this->objStream->readStringLengthEncodedNullTerminated();
		$this->intServerPort = $this->objStream->readUnsignedShort();
		$this->blnIsSSL = $this->objStream->readBoolean();
		$this->intNumberOfHeaders = $this->objStream->readUnsignedShort();
	}


	private function readHeaders()
	{
		DebugPrinter::debug('numberOfHeaders: ' . $this->intNumberOfHeaders . PHP_EOL);
		DebugPrinter::debug('HEADERS FOLLOWING' . PHP_EOL . '---------------' . PHP_EOL);

		for($i = 0; $i < $this->intNumberOfHeaders; ++$i)
		{
			$objCurrentHeader   = new AJP13MessageHeader($this->objStream);
			$this->arrHeaders[] = $objCurrentHeader;
			if(DebugPrinter::getLevel() & DebugLevel::DEBUG)
			{
				DebugPrinter::printKeyValue('header name', $objCurrentHeader->getHeaderName());
				DebugPrinter::printKeyValue('  header value', $objCurrentHeader->getHeaderValue());
			}
		}

		DebugPrinter::debug('---------------' . PHP_EOL . 'END OF HEADERS' . PHP_EOL);
	}


	private function readAttributes()
	{
		do
		{
			$objCurrentAttribute   = new AJP13Attribute($this->objStream);
			$this->arrAttributes[] = $objCurrentAttribute;

			if($objCurrentAttribute->getType() === AJP13Attribute::QUERY_STRING)
			{
				$this->parseQueryString($objCurrentAttribute);
			}

			DebugPrinter::trace('found attribute with type: '
				 . $objCurrentAttribute->getType()
				 . ', name: '
				 . $objCurrentAttribute->getName()
				 . ' and value: '
				 . $objCurrentAttribute->getValue() . PHP_EOL);

		}
		while($objCurrentAttribute->getType() !== AJP13Attribute::ARE_DONE);
	}


	/**
	 * Appends the given data to this object's current raw data. Used for combining chunked POST data.
	 *
	 * @param string $strData
	 */
	public function appendRawData(string $strData)
	{
		DebugPrinter::trace('appending data with lenght: "'. strlen($strData) .'"');

		$this->strData .= $strData;
	}
}