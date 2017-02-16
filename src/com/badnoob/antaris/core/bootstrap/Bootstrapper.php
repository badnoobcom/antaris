<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\core\bootstrap;

use com\badnoob\antaris\utils\debugging\DebugLevel;
use com\badnoob\antaris\utils\debugging\DebugPrinter;
use com\badnoob\antaris\utils\debugging\profiling\Profiler;

class Bootstrapper
{
	const PROFILING_CHANNEL = 'Bootstrapper::AUTO_LOADER';

	public function __construct()
	{
		spl_autoload_register(__CLASS__.'::autoLoader');

		$arrInitialFiles = [
			'com\\badnoob\\antaris\\utils\\debugging\\DebugPrinter',
			'com\\badnoob\\antaris\\utils\\debugging\\DebugLevel',
			'com\\badnoob\\antaris\\utils\\debugging\\profiling\\Profiler',
		];
		foreach($arrInitialFiles as $strClassName)
		{
			Bootstrapper::includeClass($strClassName);
		}

//		DebugPrinter::setLevel(DebugLevel::TRACE|DebugLevel::DEBUG|DebugLevel::INFO|DebugLevel::ERROR|DebugLevel::WARN|DebugLevel::FATAL);
		DebugPrinter::setLevel(DebugLevel::INFO|DebugLevel::ERROR|DebugLevel::WARN|DebugLevel::FATAL);
	}


	/**
	 * @param string $strClassName
	 */
	private static function includeClass(string $strClassName)
	{
		$strClassName = str_replace('\\', DIRECTORY_SEPARATOR, $strClassName). '.php';
		include_once getcwd() . DIRECTORY_SEPARATOR . $strClassName;
	}


	/**
	 * @param string $strClassName
	 */
	private static function autoLoader(string $strClassName)
	{
		Profiler::getInstance()->startChannel(self::PROFILING_CHANNEL);

		DebugPrinter::debug('request for "'. $strClassName .'"'. PHP_EOL);

		Bootstrapper::includeClass($strClassName);

		Profiler::getInstance()->endChannel(self::PROFILING_CHANNEL);
	}
}