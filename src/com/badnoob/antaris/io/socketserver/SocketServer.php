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

use com\badnoob\antaris\core\datatypes\StringWrapper;
use com\badnoob\antaris\core\servlet\ServletRegistry;
use com\badnoob\antaris\exceptions\ServletException;
use com\badnoob\antaris\io\ajp13\AJP13Constants;
use com\badnoob\antaris\io\ajp13\AJP13Message;
use com\badnoob\antaris\io\ajp13\response\AJP13ResponseFactory;
use com\badnoob\antaris\io\http\HttpResponse;
use com\badnoob\antaris\io\http\request\HttpRequestFactory;
use com\badnoob\antaris\io\streams\ByteStream;
use com\badnoob\antaris\utils\debugging\DebugPrinter;
use com\badnoob\antaris\utils\debugging\profiling\Profiler;

class SocketServer
{
	const PROFILING_CHANNEL_REQ_TOTAL = 'SocketServer::REQUEST_TOTAL';
	const PROFILING_CHANNEL_SERVLET_RUN = 'Serlvet::RUN';

	/**
	 * @var SocketServerClient[]
	 */
	private $arrSocketClients;

	/**
	 * The socket descriptors of clients which are waiting for data.
	 *
	 * @var resource[]
	 */
	private $arrSocketClientsRXWaiting;

	/**
	 * The socket descriptors of clients which are waiting to write into the socket.
	 *
	 * @var resource[]
	 */
	private $arrSocketClientsTXWaiting;

	/**
	 * @var resource[]
	 */
	private $arrRxSockets;

	/**
	 * @var resource[]
	 */
	private $arrTxSockets;

	/**
	 * @var resource[]
	 */
	private $arrExceptionSockets;

	/**
	 * @var ServletRegistry
	 */
	private $objServletRegistry;

	/**
	 * @var resource
	 */
	private $objSocketResource;


	public function __construct()
	{
		$strOS      = new StringWrapper(PHP_OS);
		$strOSLower = new StringWrapper($strOS->toLowerCase());
		$blnIsUnix  = !$strOSLower->startsWith('win');
		DebugPrinter::info('OS: ' . PHP_OS . PHP_EOL);

		if(!$blnIsUnix) die('Only Unix systems are supported so far.');

		$intDomain   = AF_INET;
		$intType     = SOCK_STREAM;
		$intProtocol = SOL_TCP;
		$intPort     = SocketServerConstants::PORT;
		$strAddress  = SocketServerConstants::IP;

		$this->createServerSocket($intDomain, $intType, $intProtocol, $strAddress, $intPort);

		$this->objServletRegistry = new ServletRegistry();
		$this->objServletRegistry->updateServlets();
		$this->arrSocketClients          = [];
		$this->arrSocketClientsRXWaiting = [];
		$this->arrSocketClientsTXWaiting = [];
		$this->prepareSocketArrays();
	}


	public function run()
	{
		while(true)
		{
			$arrReceivingSockets    = $this->arrRxSockets;
			$arrTransmittingSockets = $this->arrTxSockets;
			$arrExceptionSockets    = null;//$this->arrExceptionSockets;

			$intDirtySockets = socket_select($arrReceivingSockets,
											 $arrTransmittingSockets,
											 $arrExceptionSockets,
											 SocketServerConstants::SOCKET_SELECT_TIMEOUT);
			if($intDirtySockets >= 0)
			{
				DebugPrinter::debug('socket_select returned ['
								   . $intDirtySockets
								   . '] events'
								   . PHP_EOL);

				$this->processReceivingSocketsAsWorkers($arrReceivingSockets);
				$this->processTransmittingSockets($arrTransmittingSockets);
//				$this->processExceptionSockets($arrExceptionSockets);
				DebugPrinter::debug('End of socket_select. dirty sockets was [' . $intDirtySockets . ']');
			}
			else
			{
				DebugPrinter::warn('type of socket_select return value: '
								   . gettype($intDirtySockets)
								   . (is_int($intDirtySockets) ? ', value: ' . $intDirtySockets : ''));
			}
			DebugPrinter::debug('dirty sockets [' . $intDirtySockets . ']');

			$this->prepareSocketArrays();
		}
	}


	/**
	 * @param int    $intDomain
	 * @param int    $intType
	 * @param int    $intProtocol
	 * @param string $strAddress
	 * @param int    $intPort
	 */
	private function createServerSocket(int $intDomain,
										int $intType,
										int $intProtocol,
										string $strAddress,
										int $intPort)
	{
		$this->objSocketResource = socket_create($intDomain, $intType, $intProtocol);
		if($this->objSocketResource === false)
		{
			DebugPrinter::error('Socket resource couldn\'t be created. Exiting.' . PHP_EOL);
			exit;
		}

		$blnOptionSet = socket_set_option($this->objSocketResource, SOL_SOCKET, SO_REUSEADDR, 1);
		if($blnOptionSet === false)
		{
			DebugPrinter::error('Couldn\'t set socket options. Exiting.');
			exit;
		}

		DebugPrinter::info('trying to bind to ' . $strAddress . ':' . $intPort . PHP_EOL);
		$blnIsBound = socket_bind($this->objSocketResource, $strAddress, $intPort);
		if($blnIsBound === false)
		{
			DebugPrinter::error('Socket couldn\'t be bound to '
								. $strAddress
								. ':'
								. $intPort
								. PHP_EOL);
			exit;
		}

		DebugPrinter::debug('setting non-blocking mode ...');
		$blnIsNonBlocking = socket_set_nonblock($this->objSocketResource);
		if($blnIsNonBlocking === false)
		{
			DebugPrinter::error('Couldn\'t set non-blocking mode.'
								  . socket_last_error($this->objSocketResource));
			exit;
		}

		$blnIsListening = socket_listen($this->objSocketResource);
		if($blnIsListening === false)
		{
			DebugPrinter::error('Couldn\'t listen to socket. Last error:'
								. socket_last_error($this->objSocketResource)
								. PHP_EOL);
			exit;
		}

		DebugPrinter::info('Listening for incoming connections on '
							  . $strAddress
							  . ':'
							  . $intPort
							  . '...'
							  . PHP_EOL);
	}


	private function prepareSocketArrays()
	{
		DebugPrinter::trace('cleaning sockets...');

		$this->arrRxSockets        = array_merge([$this->objSocketResource], $this->arrSocketClientsRXWaiting);
		$this->arrExceptionSockets = [$this->objSocketResource];
		$this->arrTxSockets        = array_merge([$this->objSocketResource], $this->arrSocketClientsTXWaiting);
	}


	/**
	 * @param array $arrRxSockets
	 */
	private function processReceivingSocketsAsWorkers(array $arrRxSockets)
	{
		foreach($arrRxSockets as $objSocket)
		{
			$objWorker = $this->_getWorkerForSocket($objSocket);

			DebugPrinter::debug('processing workers...');

			try
			{
				$objWorker->process();

				$intWorkerProcessingState = $objWorker->getProcessingState();
				switch($intWorkerProcessingState)
				{
					case SocketServerWorker::WORKER_STATE_PROCESSED:
					{
						$strResponse = $this->handleProcessedPacket($objWorker);
					}
					break;

					case SocketServerWorker::WORKER_STATE_WAITING_FOR_BODY:
					{
						$strResponse = $this->handleWaitingForBody($objWorker);
					}
					break;

					case SocketServerWorker::WORKER_STATE_WAITING_FOR_SOCKET:
					{
						$this->addWorkerToRXSocketsArray($objWorker);
					}
					break;

					default:
					{
						$strResponse = AJP13ResponseFactory::prepareFromException($objWorker->getLastException());
					}
					break;
				}
			}
			catch(\Throwable $objException)
			{
				$strResponse              = AJP13ResponseFactory::prepareFromException($objException);
				$intWorkerProcessingState = SocketServerWorker::WORKER_STATE_ERROR;
			}

			if(isset($strResponse))
			{
				$objWorker->setResponse($strResponse);
				$this->addWorkerToTXSocketsArray($objWorker);
			}

			DebugPrinter::debug('worker state:' . $intWorkerProcessingState);
			if($intWorkerProcessingState !== SocketServerWorker::WORKER_STATE_WAITING_FOR_BODY
			   && $intWorkerProcessingState !== SocketServerWorker::WORKER_STATE_WAITING_FOR_SOCKET
			)
			{
				$this->removeWorkerFromRXSocketsArray($objWorker);
			}
		}
	}


	/**
	 * @param resource $objSocket
	 *
	 * @return SocketServerWorker
	 */
	private function _getWorkerForSocket($objSocket)
	{
		$objAcceptedSocketConnection = null;

		if($objSocket === $this->objSocketResource)
		{
			$objAcceptedSocketConnection = socket_accept($objSocket);

			if($objAcceptedSocketConnection === false)
			{
				DebugPrinter::error('error while accepting new connection: '
									. socket_last_error($objSocket)
									. PHP_EOL);
			}

			$intSocketId = (int)$objAcceptedSocketConnection;
		}
		else
		{
			$intSocketId = (int)$objSocket;
		}

		if(!array_key_exists($intSocketId, $this->arrSocketClients))
		{
			Profiler::getInstance()->initializeSession();
			Profiler::getInstance()->startChannel(self::PROFILING_CHANNEL_REQ_TOTAL);

			$objWorker                              = new SocketServerWorker($objAcceptedSocketConnection);
			$this->arrSocketClients[ $intSocketId ] = $objWorker;
			DebugPrinter::debug('worker created for Socket ID [' . $intSocketId . ']');
		}
		else
		{
			$objWorker = $this->arrSocketClients[ $intSocketId ];
		}

		return $objWorker;
	}


	/**
	 * @param \com\badnoob\antaris\io\ajp13\AJP13Message $objAJP13Message
	 *
	 * @return string
	 * @throws \com\badnoob\antaris\exceptions\ServletException
	 */
	private function prepareHttpResponse(AJP13Message $objAJP13Message):string
	{
		$objResponse = new HttpResponse();

		$strRequestURI  = $objAJP13Message->getRequestURI();
		$objHttpRequest = HttpRequestFactory::buildFromAJP13Message($objAJP13Message);
		$objServlet     = $this->objServletRegistry->getServletByRoute($strRequestURI);
		$strResponse    = '';

		if($objServlet)
		{
			try
			{
				$objServlet->injectRequest($objHttpRequest);
				$objServlet->injectResponse($objResponse);
			}
			catch(\Throwable $objException)
			{
				throw new ServletException(ServletException::INCOMPATIBLE_SERVLET, 0, $objException);
			}

			try
			{
				Profiler::getInstance()->startChannel(self::PROFILING_CHANNEL_SERVLET_RUN);
				$objServlet->run();
				Profiler::getInstance()->endChannel(self::PROFILING_CHANNEL_SERVLET_RUN);
			}
			catch(\Throwable $objException)
			{
				throw new ServletException(ServletException::RUNTIME_EXCEPTION . '[' . get_class($objServlet) . ']',
										   0, $objException);
			}

			$strResponse = AJP13ResponseFactory::prepareFromHttpResponse($objServlet->getResponse());
		}

		return $strResponse;
	}


	private function addWorkerToRXSocketsArray(SocketServerWorker $objWorker)
	{
		$intSocketId = (int)$objWorker->getClient()->getSocketResource();
		DebugPrinter::debug('adding socket to RX array ' . $intSocketId);
		if(!isset($this->arrSocketClientsRXWaiting[ $intSocketId ]))
		{
			$this->arrSocketClientsRXWaiting[ $intSocketId ] = $objWorker->getClient()->getSocketResource();
		}
	}


	private function addWorkerToTXSocketsArray(SocketServerWorker $objWorker)
	{
		$intSocketId = (int)$objWorker->getClient()->getSocketResource();
		DebugPrinter::debug('adding socket to TX array ' . $intSocketId);
		if(!isset($this->arrSocketClientsTXWaiting[ $intSocketId ]))
		{
			$this->arrSocketClientsTXWaiting[ $intSocketId ] = $objWorker->getClient()->getSocketResource();
		}
	}


	private function removeWorkerFromRXSocketsArray(SocketServerWorker $objWorker)
	{
		$intSocketId = (int)$objWorker->getClient()->getSocketResource();
		DebugPrinter::debug('removing socket from RX array ' . $intSocketId);
		if(isset($this->arrSocketClientsRXWaiting[ $intSocketId ]))
		{
			unset($this->arrSocketClientsRXWaiting[ $intSocketId ]);
		}
	}


	/**
	 * @param array $arrTxSockets
	 */
	private function processTransmittingSockets(array $arrTxSockets)
	{
		foreach($arrTxSockets as $objSocket)
		{
			$intSocketID = (int)$objSocket;
			$objWorker   = $this->_getWorkerForSocket($objSocket);
			DebugPrinter::debug('processing TX socket [' . $intSocketID . ']');

			try
			{
				$objOutputStream         = $objWorker->getClient()->getOutputStream();
				$intOutputStreamPosition = $objOutputStream->getPosition();

				$objOutputStream->setPosition(0);
				//get message length
				$objOutputStream->setPosition($intOutputStreamPosition + 2);
				$intToRead = $objOutputStream->readUnsignedShort() + 4;//always add 4 bytes for header signature and length

				if($intToRead > AJP13Constants::MAX_PACKET_SIZE)
				{
					DebugPrinter::printFatalWithDump('read size ['. $intToRead .'] > max_send_size ['. AJP13Constants::MAX_SEND_SIZE .']', $objOutputStream, true);
				}

				$objOutputStream->setPosition($intOutputStreamPosition);
				$strChunk = $objOutputStream->readString($intToRead);
				$intWritten = $objWorker->writeSocket($strChunk);
				$intOutputStreamPosition += $intWritten;
				$objOutputStream->setPosition($intOutputStreamPosition);

				$this->finalizeTransmission($objOutputStream, $objWorker, $intSocketID);
			}
			catch(\Throwable $objException)
			{
				DebugPrinter::fatal(AJP13ResponseFactory::EXCEPTION_MESSAGE . PHP_EOL . $objException);
				$this->removeWorkerFromTXSocketsArray($objWorker ?? null);
				unset($objWorker);
			}
		}
	}


	private function removeWorkerFromTXSocketsArray(SocketServerWorker $objWorker)
	{
		$intSocketId = (int)$objWorker->getClient()->getSocketResource();
		DebugPrinter::debug('removing socket from TX array ' . $intSocketId);
		if(isset($this->arrSocketClientsTXWaiting[ $intSocketId ]))
		{
			unset($this->arrSocketClientsTXWaiting[ $intSocketId ]);
		}
	}


	private function printProcessingProfilingInformation()
	{
		Profiler::getInstance()->endChannel(self::PROFILING_CHANNEL_REQ_TOTAL);
		Profiler::getInstance()->finishSession();
		DebugPrinter::debug('==========================');
		DebugPrinter::debug('P R O F I L I N G  timings');
		$arrTimings = Profiler::getInstance()->getTimings();
		foreach($arrTimings as $strKey => $intValue)
		{
			DebugPrinter::printKeyValue('  ' . $strKey, Profiler::formatMicrotime($intValue));
		}
		DebugPrinter::debug('==========================');
	}


	/**
	 * @param array $arrExceptionSockets
	 */
	private function processExceptionSockets(array $arrExceptionSockets)
	{
		foreach($arrExceptionSockets as $objSocket)
		{
			$intSocketID = (int)$objSocket;
			DebugPrinter::debug('processing Exception socket [' . $intSocketID . ']');
			DebugPrinter::debug('data: ' . socket_read($objSocket, AJP13Constants::MAX_PACKET_SIZE));
			$intLastError = socket_last_error($objSocket);
			DebugPrinter::error('last error: '
								. $intLastError
								. ' :: '
								. socket_strerror($intLastError));
		}
	}


	/**
	 * @param SocketServerWorker $objWorker
	 *
	 * @return string
	 */
	private function handleProcessedPacket(SocketServerWorker $objWorker): string
	{
		try
		{
			$strResponse = $this->prepareHttpResponse($objWorker->getClientMessage());
		}
		catch(\Throwable $objException)
		{
			$strResponse = AJP13ResponseFactory::prepareFromException(
				new ServletException(ServletException::GENERIC_EXCEPTION, 0, $objException));
		}

		return $strResponse;
	}


	/**
	 * @param SocketServerWorker $objWorker
	 *
	 * @return string
	 */
	private function handleWaitingForBody(SocketServerWorker $objWorker): string
	{
		$this->addWorkerToRXSocketsArray($objWorker);
		$intDataLengthToRequest = $objWorker->getDataLengthToRequestForPost();
		DebugPrinter::debug('requesting additional body data. length: ' . $intDataLengthToRequest);
		$strResponse = AJP13ResponseFactory::requestAdditionalBodyData($intDataLengthToRequest);

		return $strResponse;
	}


	/**
	 * @param \com\badnoob\antaris\io\streams\ByteStream              $objOutputStream
	 * @param \com\badnoob\antaris\io\socketserver\SocketServerWorker $objWorker
	 * @param int                                                     $intSocketID
	 */
	private function finalizeTransmission(ByteStream $objOutputStream, SocketServerWorker $objWorker, int $intSocketID)
	{
		if($objOutputStream->getBytesAvailable() > 0) return;

		$intWorkerProcessingState = $objWorker->getProcessingState();
		DebugPrinter::debug('Transmission done. Worker state: 0x'
							. strtoupper(dechex($intWorkerProcessingState)));
		$this->removeWorkerFromTXSocketsArray($objWorker);
		$objOutputStream->reset();

		if($intWorkerProcessingState === SocketServerWorker::WORKER_STATE_PROCESSED
		   || $intWorkerProcessingState === SocketServerWorker::WORKER_STATE_ERROR
		)
		{
			DebugPrinter::debug('unsetting worker with socketId [' . $intSocketID . '].');
			unset($this->arrSocketClients[ $intSocketID ], $objWorker);

			$this->printProcessingProfilingInformation();
		}
	}
}