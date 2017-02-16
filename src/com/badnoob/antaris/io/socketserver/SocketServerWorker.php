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


use com\badnoob\antaris\io\ajp13\AJP13Constants;
use com\badnoob\antaris\io\ajp13\AJP13Message;
use com\badnoob\antaris\io\ajp13\response\AJP13Response;
use com\badnoob\antaris\io\streams\ByteStream;
use com\badnoob\antaris\utils\debugging\DebugLevel;
use com\badnoob\antaris\utils\debugging\DebugPrinter;
use com\badnoob\antaris\utils\debugging\profiling\Profiler;
use com\badnoob\antaris\utils\debugging\StackTraceCollapser;
use com\badnoob\antaris\utils\objectpooling\AJP13Pool;

class SocketServerWorker
{
	const PROFILING_CHANNEL_READ_FROM_SOCKET   = 'SocketServerWorker::READ_FROM_SOCKET';
	const PROFILING_CHANNEL_WAITING_FOR_SOCKET = 'SocketServerWorker::WAITING_FOR_SOCKET';
	const WORKER_STATE_COMPLETE                = 0x05;
	const WORKER_STATE_ERROR                   = 0xFF;
	const WORKER_STATE_PROCESSED               = 0x01;
	const WORKER_STATE_WAITING_FOR_BODY        = 0x02;
	const WORKER_STATE_WAITING_FOR_SOCKET      = 0x03;
	const WORKER_STATE_WRITE_RESPONSE          = 0x04;
	/**
	 * @var int
	 */
	private $intProcessingState = self::WORKER_STATE_WAITING_FOR_SOCKET;

	/**
	 * @var int
	 */
	private $intDataLengthToRequestForPost;

	/**
	 * @var SocketServerClient
	 */
	private $objClient;

	/**
	 * @var \Exception
	 */
	private $objLastException;

	/**
	 * @var AJP13Message
	 */
	private $objRequest;

	/**
	 * @var AJP13Response
	 */
	private $objResponse;


	/**
	 * @param resource $objAcceptedSocketConnection
	 */
	public function __construct($objAcceptedSocketConnection)
	{
		socket_getpeername($objAcceptedSocketConnection, $strAddress);
		DebugPrinter::info('got new connection from ' . $strAddress . PHP_EOL);

		socket_set_nonblock($objAcceptedSocketConnection);

		DebugPrinter::debug('creating SocketServerClient');
		$this->objClient = new SocketServerClient($objAcceptedSocketConnection);
		DebugPrinter::debug('done');
	}


	public function __destruct()
	{
		DebugPrinter::debug('unsetting worker');
		if(isset($this->objRequest))
		{
			$this->objRequest->reset();
			AJP13Pool::freeAJP13Message($this->objRequest);
		}

		if(isset($this->objResponse))
		{
			AJP13Pool::freeAJP13Response($this->objResponse);
		}

		$this->intProcessingState = self::WORKER_STATE_WAITING_FOR_SOCKET;

		unset(
			$this->objClient,
			$this->objLastException,
			$this->objRequest,
			$this->objResponse);
	}


	/**
	 * @return void
	 */
	public function process()
	{
		DebugPrinter::info('process');
		$objClientInputStream = $this->objClient->getInputStream();
		if(!isset($this->objRequest))
		{
			$this->readFromSocket();
			if($objClientInputStream->getLength() === 0)
			{
				Profiler::getInstance()->startChannel(self::PROFILING_CHANNEL_WAITING_FOR_SOCKET);
				$this->setProcessingState(self::WORKER_STATE_WAITING_FOR_SOCKET);

				return;
			}
			else if(Profiler::getInstance()->hasChannel(self::PROFILING_CHANNEL_WAITING_FOR_SOCKET))
			{
				Profiler::getInstance()->endChannel(self::PROFILING_CHANNEL_WAITING_FOR_SOCKET);
			}

			$intInputStreamPosition = $objClientInputStream->getPosition();
			$this->objClient->getInputStream()->setPosition(0);
			if (DebugPrinter::getLevel() & DebugLevel::TRACE)
			{
				DebugPrinter::trace('InputStream following:'
									. PHP_EOL
									. DebugPrinter::getStringAsHexDump($objClientInputStream->readAll()));
			}
			$objClientInputStream->setPosition($intInputStreamPosition);

			$this->objRequest = $this->getAJP13MessageFromBuffer();
		}

		if($this->needsPostData())
		{
			$this->readAdditionalBodyData($objClientInputStream);

			return;
		}

		$this->setProcessingState(self::WORKER_STATE_PROCESSED);
	}


	private function readFromSocket()
	{
		Profiler::getInstance()->startChannel(self::PROFILING_CHANNEL_READ_FROM_SOCKET);

		$objClientSocket      = $this->objClient->getSocketResource();
		$objClientInputStream = $this->objClient->getInputStream();
		DebugPrinter::debug('readFromSocket. bufferPosition before: ' . $objClientInputStream->getPosition());

		while(true)
		{
			$strCurrentPacket = $this->readPacket($objClientSocket);
			DebugPrinter::trace('read packet with length "' . strlen($strCurrentPacket) . '"');

			if($strCurrentPacket === '') break;

			$objClientInputStream->writeString($strCurrentPacket);
		}

		DebugPrinter::debug('bufferPosition after read: ' . $objClientInputStream->getPosition());
		Profiler::getInstance()->endChannel(self::PROFILING_CHANNEL_READ_FROM_SOCKET);
	}


	/**
	 * @param resource $objAcceptedSocketConnection
	 *
	 * @return string
	 */
	public function readPacket($objAcceptedSocketConnection):string
	{
		$strCurrentPacket = '';
		$strBuffer = socket_read($objAcceptedSocketConnection, AJP13Constants::MAX_PACKET_SIZE, PHP_BINARY_READ);

		if(DebugPrinter::getLevel() & DebugLevel::TRACE)
		{
			DebugPrinter::trace('socket_read ... | '
								. PHP_EOL
								. StackTraceCollapser::getCollapsedStackTrace());

			DebugPrinter::trace('type of socket_read return value: ' . gettype($strBuffer) . (is_string($strBuffer)
									? ', len: ' . strlen($strBuffer) : ''));
		}

		if($strBuffer === false)
		{
			$intErrorNumber = socket_last_error($objAcceptedSocketConnection);
			socket_clear_error($objAcceptedSocketConnection);
			if($intErrorNumber !== SOCKET_EAGAIN)
			{
				DebugPrinter::error('Last error: ' . $intErrorNumber . ' - "' . socket_strerror($intErrorNumber) . '"');
			}

			return $strCurrentPacket;
		}

		DebugPrinter::debug('read from socket... [' . strlen($strBuffer) . '] Bytes');
		$strCurrentPacket .= $strBuffer;

		return $strCurrentPacket;
	}


	private function setProcessingState(int $intState)
	{
		$this->intProcessingState = $intState;
		if(DebugPrinter::getLevel() & DebugLevel::TRACE)
		{
			DebugPrinter::trace('Set worker state: 0x'
								. dechex($intState)
								. ' | '
								. PHP_EOL
								. StackTraceCollapser::getCollapsedStackTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
		}
	}


	/**
	 * @return AJP13Message
	 */
	public function getAJP13MessageFromBuffer():AJP13Message
	{
		DebugPrinter::debug('getAJP13MessageFromBuffer');
		if(!isset($this->objRequest))
		{
			$objClientInputStream = $this->objClient->getInputStream();
			$objClientInputStream->setPosition(0);

			if($objClientInputStream->getBytesAvailable() < AJP13Constants::HEADER_SIZE)
			{
				DebugPrinter::error('processRXSockets:: buffer bytes available only [' .
									$objClientInputStream->getBytesAvailable() .
									'], where we need ' . AJP13Constants::HEADER_SIZE);

				return null;
			}

			$objAJP13Message = $this->parseBufferToAJP13Message($objClientInputStream, $this->getProcessingState());
			DebugPrinter::trace('setting client message');
			$this->objClient->setMessage($objAJP13Message);
		}
		else
		{
			$objAJP13Message = $this->objClient->getMessage();
		}

		return $objAJP13Message;
	}


	/**
	 * @param ByteStream $objClientBuffer
	 * @param int $intProcessingState
	 *
	 * @return AJP13Message
	 */
	private function parseBufferToAJP13Message(ByteStream $objClientBuffer, int $intProcessingState)
	{
		$objAJP13Message = AJP13Pool::getAJP13Message();
		$objAJP13Message->setStream($objClientBuffer);
		$objAJP13Message->readHeaderAndLength();
		$objAJP13Message->parseMessage($intProcessingState);

		return $objAJP13Message;
	}


	/**
	 * @return bool
	 */
	private function needsPostData():bool
	{
		return $this->intProcessingState === self::WORKER_STATE_WAITING_FOR_BODY
			   || $this->objRequest->getMethod() === AJP13Message::HTTP_METHOD_POST;
	}


	private function retrievePostData()
	{
		DebugPrinter::debug('retrievePostData');
		$objClient            = $this->objClient;
		$objClientInputStream = $objClient->getInputStream();
		while($objClientInputStream->getBytesAvailable())
		{
			DebugPrinter::trace('bufferBytesAvailable: ' . $objClientInputStream->getBytesAvailable());
			$objAJP13DataMessage = $this->parseBufferToAJP13Message($objClientInputStream, $this->getProcessingState());
			$this->appendDataChunkToBody($objAJP13DataMessage);
		}

		$intBodyLength    = $objClient->getBodyLength();
		$intContentLength = $objClient->getMessage()->getContentLength();
		DebugPrinter::debug('bodyLength: '
								. $intBodyLength
								. ', contentLength: '
								. $intContentLength
								. ',	bytesAvailable in stream: '
								. $objClient->getInputStream()->getBytesAvailable());

		if($intBodyLength === $intContentLength)
		{
			$this->setProcessingState(self::WORKER_STATE_PROCESSED);
		}
		else
		{
			$this->intDataLengthToRequestForPost = $intContentLength - $intBodyLength;
			$this->setProcessingState(self::WORKER_STATE_WAITING_FOR_BODY);
		}
	}


	private function appendDataChunkToBody(AJP13Message $objDataChunk)
	{
		$strBodyData = $objDataChunk->getData();
		$this->objClient->appendBodyData($strBodyData);
	}


	/**
	 * @param string $strResponse
	 */
	public function setResponse(string $strResponse)
	{
		$this->objClient->getOutputStream()->writeString($strResponse);
		$this->objClient->getOutputStream()->setPosition(0);
	}


	/**
	 * @param string $strResponse
	 *
	 * @return int number of bytes written into socket
	 * @throws SocketException
	 */
	public function writeSocket(string $strResponse):int
	{
		$intWritten = socket_write($this->objClient->getSocketResource(), $strResponse);
		if($intWritten === false)
		{
			$intErrorCode    = socket_last_error($this->objClient->getSocketResource());
			$strErrorMessage = 'Couldn\'t write to socket: '
							   . $intErrorCode
							   . ' - '
							   . socket_strerror($intErrorCode);
			DebugPrinter::error($strErrorMessage);
			throw new SocketException($strErrorMessage);
		}

		DebugPrinter::debug('written into socket: '
							. $intWritten
							. '/'
							. strlen($strResponse)
							. PHP_EOL);
		return $intWritten;
	}


	public function getProcessingState():int{ return $this->intProcessingState; }


	public function getDataLengthToRequestForPost():int{ return $this->intDataLengthToRequestForPost; }


	public function getClient():SocketServerClient
	{
		return $this->objClient;
	}


	/**
	 * @return ByteStream
	 */
	public function getClientInputStream():ByteStream
	{
		return $this->objClient->getInputStream();
	}


	/**
	 * @return AJP13Message
	 */
	public function getClientMessage():AJP13Message
	{
		return $this->objClient->getMessage();
	}


	public function getLastException():\Exception
	{
		return $this->objLastException;
	}


	/**
	 * @return AJP13Response
	 */
	public function getResponse():AJP13Response
	{
		return $this->objResponse;
	}


	/**
	 * @param ByteStream $objClientInputStream
	 */
	private function readAdditionalBodyData(ByteStream $objClientInputStream)
	{
		$intInputStreamPosition = $objClientInputStream->getPosition();
		$this->readFromSocket();
		$objClientInputStream->setPosition($intInputStreamPosition);
		$this->retrievePostData();
	}
}