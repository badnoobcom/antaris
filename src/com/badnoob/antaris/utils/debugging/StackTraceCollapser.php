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


class StackTraceCollapser
{
	public static function getCollapsedStackTrace(): string
	{
		$arrStackTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

		$strReturnValue = '';
		$strIndentChar  = ' ';
		$strIndentation = $strIndentChar;
		// starting at 1, so we don't print this class in the stack
		foreach($arrStackTrace as $arrCurrentEntry)
		{
			$strReturnValue .= $strIndentation
							   . $arrCurrentEntry['class']
							   . '::'
							   . $arrCurrentEntry['function']
							   . ':'
							   . $arrCurrentEntry['line']
							   . PHP_EOL;
			$strIndentation .= $strIndentChar;
		}

		return $strReturnValue;
	}
}