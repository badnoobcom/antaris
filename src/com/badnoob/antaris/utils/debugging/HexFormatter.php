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


class HexFormatter
{
	public function formatToHexDump(string $strValue):string
	{
		$strToReturn = '';
		$arrSplit = str_split($strValue, 16);

		for($i = 0, $intLen = count($arrSplit); $i < $intLen; ++$i)
		{
			$strCurrentSplitPart = $arrSplit[$i];
			$strHexPosition = strtoupper(dechex($i * 0x10));
			while(strlen($strHexPosition) < 4)
			{
				$strHexPosition = '0'. $strHexPosition;
			}

			$strHexPairs = $this->convertStringToHexPairs($strCurrentSplitPart);

			$strCurrentSplitPart = $this->replaceBinaryCharsWithDot($strCurrentSplitPart);

			$strCurrentLine = $strHexPosition .'    '. $strHexPairs .' - '. $strCurrentSplitPart . PHP_EOL;

			$strToReturn .= $strCurrentLine;
		}

		return $strToReturn;
	}


	private function convertStringToHexPairs(string $strValue):string
	{
		$strHexPairs = '';
		for($i = 0; $i < 0xF; ++$i)
		{
			$strCurrentHexPair = strtoupper(dechex(ord(substr($strValue, $i, 1))));
			while(strlen($strCurrentHexPair) < 2)
			{
				$strCurrentHexPair = '0'. $strCurrentHexPair;
			}

			$strHexPairs .= $strCurrentHexPair .' ';
		}

		return $strHexPairs;
	}


	private function replaceBinaryCharsWithDot(string $strValue):string
	{
		$strLen = strlen($strValue);
		for($i = 0; $i < $strLen; ++$i)
		{
			$intChar = ord(substr($strValue, $i, 1));
			if($intChar < 0x21 || $intChar > 0x7E)//smaller than ! or higher than ~
			{
				$strValue = substr_replace($strValue, '.', $i, 1);
			}
		}

		return $strValue;
	}
}