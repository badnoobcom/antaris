<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\socketserver;


use com\badnoob\antaris\io\ajp13\AJP13Message;
use com\badnoob\antaris\io\streams\ByteStream;
use com\badnoob\antaris\utils\debugging\DebugPrinter;
use com\badnoob\antaris\utils\objectpooling\ByteStreamPool;

class SocketServerClient
{
	/**
	 * @var bool
	 */
	private $blnHasBody;

	/**
	 * @var bool
	 */
	private $blnIsFirstBodyContent;

	/**
	 * @var bool
	 */
	private $blnWaitingForBody;

	/**
	 * @var int
	 */
	private $intBodyLength;

	/**
	 * @var AJP13Message
	 */
	private $objCurrentAJP13Message;

	/**
	 * @var ByteStream
	 */
	private $objInputStream;

	/**
	 * @var ByteStream
	 */
	private $objOutputStream;

	/**
	 * @var resource
	 */
	private $objSocketResource;


	/**
	 * SocketServerClient constructor.
	 *
	 * @param resource $objSocketResource
	 */
	public function __construct($objSocketResource)
	{
		$this->blnHasBody        = false;
		$this->blnWaitingForBody = false;
		$this->intBodyLength     = 0;
		$this->objInputStream    = ByteStreamPool::getByteStream();
		$this->objOutputStream	 = ByteStreamPool::getByteStream();
		$this->objSocketResource = $objSocketResource;
	}


	public function __destruct()
	{
		ByteStreamPool::freeByteStream($this->objInputStream);
		ByteStreamPool::freeByteStream($this->objOutputStream);

		if(isset($this->objSocketResource))
		{
			socket_shutdown($this->objSocketResource);
			socket_close($this->objSocketResource);
		}

		unset(
			$this->blnHasBody,
			$this->blnWaitingForBody,
			$this->blnIsFirstBodyContent,
			$this->objBodyMesssage,
			$this->objCurrentAJP13Message,
			$this->objInputStream,
			$this->objOutputStream,
			$this->objSocketResource);
	}


	/**
	 * @param string $strMessage
	 */
	public function appendBodyData(string $strMessage)
	{
		$this->objCurrentAJP13Message->appendRawData($strMessage);
		$intBodyLength = strlen($this->objCurrentAJP13Message->getData());
		$this->setBodyLength($intBodyLength);
		$this->blnHasBody = $intBodyLength > 0;

		DebugPrinter::trace('appended body data. new length: '. $this->intBodyLength);
	}


	private function setBodyLength(int $intBodyLength)
	{
		$this->intBodyLength = $intBodyLength;
	}


	/**
	 * @return \com\badnoob\antaris\io\streams\ByteStream
	 */
	public function getInputStream():ByteStream{ return $this->objInputStream; }


	/**
	 * @return \com\badnoob\antaris\io\streams\ByteStream
	 */
	public function getOutputStream():ByteStream{ return $this->objOutputStream; }


	/**
	 * @return AJP13Message
	 */
	public function getMessage():AJP13Message{ return $this->objCurrentAJP13Message; }


	/**
	 * @param AJP13Message $objMessage
	 */
	public function setMessage(AJP13Message $objMessage){ $this->objCurrentAJP13Message = $objMessage; }


	/**
	 * @return resource
	 */
	public function getSocketResource(){ return $this->objSocketResource; }


	/**
	 * @return bool
	 */
	public function getIsFirstBodyContent():bool{ return $this->blnIsFirstBodyContent; }


	/**
	 * @param $blnValue
	 */
	public function setIsFirstBodyContent(bool $blnValue){ $this->blnIsFirstBodyContent = $blnValue; }


	/**
	 * @return bool
	 */
	public function isWaitingForBody():bool{ return $this->blnWaitingForBody; }


	/**
	 * @param bool $blnValue
	 */
	public function setWaitingForBody(bool $blnValue){ $this->blnWaitingForBody = $blnValue; }


	/**
	 * @return bool
	 */
	public function hasBody():bool{ return $this->blnHasBody; }


	/**
	 * @return int
	 */
	public function getBodyLength():int
	{
		return $this->intBodyLength;
	}
}