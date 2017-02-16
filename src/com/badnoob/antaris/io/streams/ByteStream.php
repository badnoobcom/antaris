<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\streams;


use com\badnoob\antaris\utils\debugging\DebugPrinter;
use com\badnoob\antaris\utils\debugging\profiling\Profiler;

class ByteStream implements \ArrayAccess
{
	const PROFILING_CHANNEL_READ		= 'ByteStream::READ';
	const PROFILING_CHANNEL_WRITE	= 'ByteStream::WRITE';

	const BIG_ENDIAN    = 1;
	const LITTLE_ENDIAN = 2;

	/**
	 * @var string    Used as constant - must not be changed!
	 */
	const ZERO_BYTE_STRING = "\0";

	const TWO_POW_15 = 0xFFFF;
	const TWO_POW_16 = 0x10000;

	/** @var int */
	private $intEndian;
	/** @var int */
	private $intLength;
	/** @var int */
	private $intPosition;

	/** @var string */
	private $strData;

	/** @var  Profiler for performance reasons, cached instance */
	private $objProfiler;


	public function __construct()
	{
		$this->setEndian(self::BIG_ENDIAN);
		$this->intLength   = 0;
		$this->intPosition = 0;
		$this->strData     = '';
		$this->objProfiler = Profiler::getInstance();
	}


	/**
	 * @param int $intValue
	 * @throws \InvalidArgumentException
	 */
	public function setEndian(int $intValue)
	{
		if($intValue !== self::BIG_ENDIAN && $intValue !== self::LITTLE_ENDIAN)
		{
			throw new \InvalidArgumentException(__METHOD__
												. ' Endian must be of type ByteStream::BIG_ENDIAN or ByteStream::LITTLE_ENDIAN');
		}

		$this->intEndian = $intValue;
	}


	public function __destruct()
	{
		unset($this->intEndian,
			$this->intPosition,
			$this->intLength,
			$this->strData,
			$this->objProfiler);
	}


	/**
	 * @return string
	 * @throws \OutOfBoundsException
	 */
	public function readAll():string
	{
		return $this->readString($this->intLength - $this->intPosition);
	}


	/**
	 * @param int $intLengthToRead
	 *
	 * @return string
	 * @throws \OutOfBoundsException
	 */
	public function readString(int $intLengthToRead):string
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_READ);

		$this->validateAccessInBounds($this->intPosition, $intLengthToRead);

		DebugPrinter::trace('reading '
								. $intLengthToRead
								. ' bytes from position '
								. $this->intPosition
								. '->');

		$strReturnValue = substr($this->strData, $this->intPosition, $intLengthToRead);
		$this->intPosition += $intLengthToRead;

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_READ);

		return $strReturnValue;
	}


	/**
	 * @return bool
	 * @throws \OutOfBoundsException
	 */
	public function readBoolean():bool
	{
		return (bool) $this->readByte();
	}


	/**
	 * @return int
	 * @throws \OutOfBoundsException
	 */
	public function readByte():int
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_READ);

		$this->validateAccessInBounds($this->intPosition, 1);

		$arrUnpacked = unpack('c', $this->strData[ $this->intPosition++ ]);

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_READ);

		return $arrUnpacked[1];
	}


	/**
	 * @return string
	 * @throws \OutOfBoundsException
	 * @throws \UnexpectedValueException
	 */
	public function readStringLengthEncodedNullTerminated():string
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_READ);

		$intStringLength = $this->readShort();

		if($intStringLength === -1 || $intStringLength === 0xFFFF)
		{
			DebugPrinter::trace('skipping over 0-length string');
			if($this[ $this->intPosition + 1 ] === self::ZERO_BYTE_STRING)
			{
				++$this->intPosition;
			}

			$this->objProfiler->endChannel(self::PROFILING_CHANNEL_READ);

			return '';
		}

		$strValue = $this->readString($intStringLength);

		DebugPrinter::debug(
			'length: ' . $intStringLength
			. ', string: "' . $strValue . '"'
			. ', new position: ' . $this->intPosition
			. PHP_EOL
		);

		/**
		 * Using strict string equality here, since the creation of a \0 byte is still faster than executing unpack and
		 * accessing its first element.
		 */
		if($this[ $this->intPosition ] === self::ZERO_BYTE_STRING)
		{
			DebugPrinter::trace('we have a valid string!' . PHP_EOL);
		}
		else
		{
			$this->objProfiler->endChannel(self::PROFILING_CHANNEL_READ);
			throw new \UnexpectedValueException('String is not ZERO-terminated!');
		}

		++$this->intPosition;

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_READ);

		return $strValue;
	}


	/**
	 * @return int
	 * @throws \OutOfBoundsException
	 */
	public function readShort():int
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_READ);

		$this->validateAccessInBounds($this->intPosition, 2);

		DebugPrinter::trace('read short at position: ' . $this->intPosition . PHP_EOL);

		$intToReturn = unpack('n', $this->strData[$this->intPosition++].$this->strData[$this->intPosition++])[1];

		if($intToReturn > self::TWO_POW_15)
		{
			$intToReturn -= self::TWO_POW_16;
		}

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_READ);

		return $intToReturn;
	}


	/**
	 * @return int
	 * @throws \OutOfBoundsException
	 */
	public function readUnsignedShort():int
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_READ);

		$this->validateAccessInBounds($this->intPosition, 2);

		DebugPrinter::trace('read unsigned short at position: ' . $this->intPosition . PHP_EOL);

		$intUnsignedShort = unpack('n', $this->strData[$this->intPosition++].$this->strData[$this->intPosition++])[1];

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_READ);

		return $intUnsignedShort;
	}


	/**
	 * @return int
	 * @throws \OutOfBoundsException
	 */
	public function readUnsignedByte():int
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_READ);

		$this->validateAccessInBounds($this->intPosition, 1);

		$intToReturn = unpack('C', $this->strData[ $this->intPosition++ ])[1];

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_READ);

		return $intToReturn;
	}


	public function reset()
	{
		unset($this->strData);
		$intLengthBefore         = $this->intLength;
		$intBytesAvailableBefore = $this->intPosition;
		$this->strData           = '';
		$this->intLength         = 0;
		$this->intPosition		 = 0;

		DebugPrinter::debug('Resetting ByteStream. length before: '
							  . $intLengthBefore
							  . ', position before: '
							  . $intBytesAvailableBefore
							  . ', length: '
							  . $this->getLength()
							  . ', bytesAvailable: '
							  . $this->getBytesAvailable());
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
	public function getBytesAvailable():int
	{
		return $this->intLength - $this->intPosition;
	}


	/**
	 * @param int $intShortValue
	 */
	public function writeShort(int $intShortValue)
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_WRITE);

		$intShortValue &= 0xFFFF;

		$byteFirst  = ($intShortValue >> 8) & 0xFF;
		$byteSecond = $intShortValue & 0xFF;

		if($this->intEndian === self::BIG_ENDIAN)
		{
			$this->writeByte($byteFirst);
			$this->writeByte($byteSecond);
		}
		else
		{
			$this->writeByte($byteSecond);
			$this->writeByte($byteFirst);
		}

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_WRITE);
	}


	/**
	 * @param int $intByteValue
	 */
	public function writeByte(int $intByteValue)
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_WRITE);

		$intByteValue &= 0xFF;

		$this[ $this->intPosition++ ] = $intByteValue;
		$this->updateLength();

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_WRITE);
	}


	/**
	 * @param string $strValue
	 */
	public function writeStringLengthEncodedNullTerminated(string $strValue)
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_WRITE);

		//write string length first
		$intValueLength = strlen($strValue);
		$this->writeUnsignedShort($intValueLength);

		//write string, followed by Zero-termination-byte
		$this->writeString($strValue.self::ZERO_BYTE_STRING);

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_WRITE);
	}



	/**
	 * @param int $intShortValue
	 */
	public function writeUnsignedShort(int $intShortValue)
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_WRITE);

		$intShortValue &= 0xFFFF;

		$byteFirst  = ($intShortValue >> 8) & 0xFF;
		$byteSecond = $intShortValue & 0xFF;

		if($this->intEndian === self::BIG_ENDIAN)
		{
			$this->writeUnsignedByte($byteFirst);
			$this->writeUnsignedByte($byteSecond);
		}
		else
		{
			$this->writeUnsignedByte($byteSecond);
			$this->writeUnsignedByte($byteFirst);
		}

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_WRITE);
	}


	/**
	 * @param int $intByteValue
	 */
	public function writeUnsignedByte(int $intByteValue)
	{
		$this->writeByte($intByteValue);
	}


	/**
	 * @param string $strValue
	 */
	public function writeString(string $strValue)
	{
		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_WRITE);

		$intValueLength = strlen($strValue);

		if($intValueLength === 0)
		{
			DebugPrinter::trace('skipping 0 length string.');
			$this->objProfiler->endChannel(self::PROFILING_CHANNEL_WRITE);

			return;
		}

		$this->insertString($strValue, $this->intPosition);

		$this->intPosition += $intValueLength;
		$this->updateLength();

		DebugPrinter::trace('wrote string into ByteStream; length grew to '
								. $this->intLength
								. ', position is '
								. $this->intPosition
								. PHP_EOL);

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_WRITE);
	}


	private function updateLength()
	{
		if($this->intPosition > $this->intLength)
		{
			$this->intLength = $this->intPosition;
		}
	}


	/**
	 * @return int
	 */
	public function getPosition():int
	{
		return $this->intPosition;
	}


	/**
	 * @param int $intValue
	 * @throws \OutOfBoundsException
	 */
	public function setPosition(int $intValue)
	{
		$this->validatePositionInBounds($intValue);

		$this->intPosition = $intValue;
	}


	/**
	 * @return int
	 */
	public function getEndian():int
	{
		return $this->intEndian;
	}


	// -- INTERFACE RELATED -- //


	public function offsetSet($intOffset, $strValue)
	{
		$this->validatePositionInBounds($intOffset);

		$this->objProfiler->startChannel(self::PROFILING_CHANNEL_WRITE);

		if(is_numeric($strValue))
		{
			$strPackCode = 'C*';
			$strValue    = (int)$strValue;
			if($strValue < 0)
			{
				$strPackCode = 'c*';
			}

			$strValue = (string) pack($strPackCode, $strValue & 0xFF);
		}
		else if(is_string($strValue) && strlen($strValue) > 1)
		{
			$this->objProfiler->endChannel(self::PROFILING_CHANNEL_WRITE);

			throw new \InvalidArgumentException(__METHOD__
												. ' The given value "'
												. $strValue
												. '" must not exceed the length of 1.');
		}

		$this->insertString($strValue, $intOffset);

		if($intOffset === $this->intLength)
		{
			++$this->intLength;
		}

		$this->objProfiler->endChannel(self::PROFILING_CHANNEL_WRITE);
	}


	public function offsetExists($intOffset)
	{
		return $this->intLength > $intOffset;
	}


	public function offsetUnset($intOffset)
	{
		throw new \BadMethodCallException(__METHOD__ . ' ByteStream cannot have empty cells.');
	}


	public function offsetGet($intOffset)
	{
		$this->validateAccessInBounds($intOffset, 1);

		return $this->strData[$intOffset];
	}

	// -- END INTERFACE RELATED -- //

	/**
	 * @param string	$strValue
	 * @param int	$intOffset
	 */
	private function insertString(string $strValue, int $intOffset)
	{
		/**
		 * If the position is somewhere inside the ByteStream, we need to cache the first part (before the position)
		 * and the last part (after the position + strlen($strValue)).
		 * Example: ByteStream contains "HelloWorld ThisIsCool"; position is set to 4 (the o);
		 * $strValue contains ", Yeah! "; Result will be: "Hell, Yeah! ThisIsCool";
		 * Speaking of code, the $strBeforePart would be "Hell", the $strAfterPart would be "ThisIsCool"
		 */
		$strBeforePart = substr($this->strData, 0, $intOffset);

		$strAfterPart = '';
		if($intOffset + 1 < $this->intLength)
		{
			$strAfterPart = substr($this->strData, $intOffset + 1);
		}

		$this->strData = $strBeforePart . $strValue . $strAfterPart;
	}


	/**
	 * @param int	$intOffset
	 * @param int	$intLengthToRead
	 * @throws \OutOfBoundsException
	 */
	private function validateAccessInBounds(int $intOffset, int $intLengthToRead)
	{
		if($intOffset > ($this->intLength - $intLengthToRead))
		{
			throw new \OutOfBoundsException(__METHOD__
											. ' The byte stream boundary was reached. EOF. position: '
											. $this->intPosition
											. ', length: '
											. $this->intLength
											. ', to read: '
											. $intLengthToRead);
		}
	}


	/**
	 * @param int $intValue
	 * @throws \OutOfBoundsException
	 */
	private function validatePositionInBounds(int $intValue)
	{
		if($intValue > $this->intLength)
		{
			throw new \OutOfBoundsException(__METHOD__
											. ' The index '
											. $intValue
											. ' is out of bounds; must be <= '
											. $this->intLength);
		}
	}
}