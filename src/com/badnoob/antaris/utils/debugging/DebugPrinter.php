<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\utils\debugging;


use com\badnoob\antaris\io\streams\ByteStream;
use com\badnoob\antaris\utils\debugging\profiling\Profiler;

class DebugPrinter
{
	const PROFILING_CHANNEL_GETBYTEVALUES 	= 'DebugPrinter::GET_BYTE_VALUES';
	const PROFILING_CHANNEL_GETSTRINGASHEXDUMP 	= 'DebugPrinter::GET_STRING_AS_HEX_DUMP';
	const PROFILING_CHANNEL_PRINTLN       	= 'DebugPrinter::PRINT_LN';
	const TYPE_ECHO                       	= 0x01;
	const COLOR_BLUE							= "\033[1;34m";
	const COLOR_BOLD    						= "\033[1m";
	const COLOR_DARK    						= "\033[0;30m";
	const COLOR_LIGHTGRAY					= "\033[0;37m";
	const COLOR_DEFAULT 						= "\033[0m";
	const COLOR_GREEN   						= "\033[0;32m";
	const COLOR_RED     						= "\033[1;31m";
	const COLOR_PURPLE						= "\033[1;35m";
	const LEVEL_TO_COLOR_MAP     = [
		DebugLevel::DEBUG	=> self::COLOR_GREEN,
		DebugLevel::ERROR	=> self::COLOR_RED,
		DebugLevel::FATAL	=> self::COLOR_BOLD . self::COLOR_RED,
		DebugLevel::INFO		=> self::COLOR_BLUE,
		DebugLevel::TRACE	=> self::COLOR_LIGHTGRAY,
		DebugLevel::WARN		=> self::COLOR_PURPLE
	];
	const LEVEL_TO_STRING_MAP    = [
		DebugLevel::DEBUG	=> 'DEBUG',
		DebugLevel::ERROR	=> 'ERROR',
		DebugLevel::FATAL	=> 'FATAL',
		DebugLevel::INFO		=> 'INFO',
		DebugLevel::TRACE	=> 'TRACE',
		DebugLevel::WARN		=> 'WARN'
	];
	const LEVEL_STRING_SPACING   = 2;
	const LEVEL_STRING_MIN_WIDTH = 5; // all levels, except info and warn are 5. others will be padded
	const CLASS_MAX_WIDTH        = 50;

	private static $TYPE_MAP = [
		self::TYPE_ECHO
	];

	private static $intDebugLevel		= DebugLevel::FATAL | DebugLevel::ERROR | DebugLevel::WARN;
	private static $intType				= self::TYPE_ECHO;

	private static $objHexFormatter;


	/**
	 * @param string $strValue
	 *
	 * @return string
	 */
	public static function getByteValues(string $strValue):string
	{
		Profiler::getInstance()->startChannel(self::PROFILING_CHANNEL_GETBYTEVALUES);

		$i             = 0;
		$intLen        = strlen($strValue);
		$strByteValues = '';

		for(; $i < $intLen; ++$i)
		{
			$strChar = $strValue[$i];
			$strByteValues .= '"' . $strChar . '" [' . ord($strChar) . ']' . chr(10);
		}

		Profiler::getInstance()->endChannel(self::PROFILING_CHANNEL_GETBYTEVALUES);

		return $strByteValues;
	}


	public static function getStringAsHexDump(string $strValue):string
	{
		Profiler::getInstance()->startChannel(self::PROFILING_CHANNEL_GETSTRINGASHEXDUMP);

		if(!isset(self::$objHexFormatter))
		{
			self::$objHexFormatter = new HexFormatter();
		}

		$strResult = self::$objHexFormatter->formatToHexDump($strValue);

		Profiler::getInstance()->endChannel(self::PROFILING_CHANNEL_GETSTRINGASHEXDUMP);

		return $strResult;
	}


	public static function debug(string $strValue)
	{
		self::println($strValue, DebugLevel::DEBUG);
	}


	/**
	 * @param string $strValue
	 */
	public static function error(string $strValue)
	{
		self::println($strValue, DebugLevel::ERROR, true);
	}


	public static function fatal(string $strValue)
	{
		self::println($strValue, DebugLevel::FATAL);
	}


	public static function info(string $strValue)
	{
		self::println($strValue, DebugLevel::INFO);
	}


	public static function printFatalWithDump(string $strMessage, ByteStream $objToDump, bool $blnKillProgram)
	{
		self::fatal($strMessage
					.PHP_EOL
					.StackTraceCollapser::getCollapsedStackTrace()
				   );
		$intPosition = $objToDump->getPosition();
		$objToDump->setPosition(0);
		$strData = $objToDump->readAll();
		$strData = substr($strData, 0, 0x1FFF);
		self::trace(self::getStringAsHexDump($strData));
		$objToDump->setPosition($intPosition);

		if($blnKillProgram) exit();
	}


	public static function trace(string $strValue)
	{
		self::println($strValue, DebugLevel::TRACE);
	}


	public static function warn(string $strValue)
	{
		self::println($strValue, DebugLevel::WARN);
	}


	/**
	 * @param string $strValue
	 * @param int    $intDebugLevel
	 * @param bool	$blnPrintLine
	 */
	private static function println(string $strValue, int $intDebugLevel, bool $blnPrintLine = false)
	{
		if(!(self::$intDebugLevel & $intDebugLevel)) return;

		Profiler::getInstance()->startChannel(self::PROFILING_CHANNEL_PRINTLN);

		$strColor = self::LEVEL_TO_COLOR_MAP[$intDebugLevel];
		$strLevelAndClass = self::formatLevelAndClass($intDebugLevel, $blnPrintLine);

		switch(self::$intType)
		{
			case self::TYPE_ECHO:
			{
				echo $strColor . $strLevelAndClass . $strValue . self::appendEOL($strValue) . self::COLOR_DEFAULT;
			}
			break;
		}
		Profiler::getInstance()->endChannel(self::PROFILING_CHANNEL_PRINTLN);
	}


	/**
	 * @param string $strValue
	 *
	 * @return string
	 */
	private static function appendEOL(string $strValue):string
	{
		if(strrpos($strValue, PHP_EOL) !== strlen($strValue) - 1)
		{
			return PHP_EOL;
		}

		return '';
	}


	/**
	 * @param string $strKey
	 * @param string $strValue
	 */
	public static function printKeyValue(string $strKey, string $strValue)
	{
		self::println(self::COLOR_DEFAULT . $strKey . ': ' . self::COLOR_BOLD . $strValue, DebugLevel::INFO);
	}


	/**
	 * @param int $intValue
	 */
	public static function setType(int $intValue)
	{
		if(!array_key_exists($intValue, self::$TYPE_MAP))
		{
			throw new \UnexpectedValueException('Unknown type ' . $intValue);
		}

		self::$intType = $intValue;
	}


	public static function setLevel(int $intDebugLevel)
	{
		self::$intDebugLevel = $intDebugLevel;
	}

	public static function getLevel():int
	{
		return self::$intDebugLevel;
	}


	private static function formatLevelAndClass(int $intLevel, bool $blnPrintLine):string
	{
		$strLevel = self::LEVEL_TO_STRING_MAP[$intLevel];
		while(strlen($strLevel) < self::LEVEL_STRING_SPACING + self::LEVEL_STRING_MIN_WIDTH)
		{
			$strLevel .= ' ';
		}

		$arrDebugBackTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$strClassName = $arrDebugBackTrace[3]['class'];
		$strLine = '';
		if ($blnPrintLine)
		{
			$strLine = ':'. $arrDebugBackTrace[2]['line'];
		}

		$blnNeedsTrim = strlen($strClassName) > self::CLASS_MAX_WIDTH;
		if ($blnNeedsTrim)
		{
			$strTrimmed = '';
			$arrSplit = explode('\\', $strClassName);
			for ($i = 0, $intLen = count($arrSplit); $i < $intLen; ++$i)
			{
				$strItem = $arrSplit[$i];
				if($i === $intLen - 1)
				{
					$strTrimmed .= $strItem;
					continue;
				}

				if (strlen($strItem) === 1)
				{
					$strTrimmed .= $strItem . '\\';
				}
				else
				{
					$strTrimmed .= $strItem[0] . '\\';
				}
			}

			$strClassName = $strTrimmed;

		}

		return $strLevel .'['. $strClassName . $strLine .']: ';
	}
}