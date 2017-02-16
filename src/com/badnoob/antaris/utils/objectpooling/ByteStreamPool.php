<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\utils\objectpooling;

use com\badnoob\antaris\io\streams\ByteStream;

class ByteStreamPool
{

	/**
	 * @var ByteStream[]
	 */
	private static $arrByteStreamsFree = [];

	/**
	 * @var ByteStream[];
	 */
	private static $arrByteStreamsUsed = [];


	/**
	 * @return ByteStream
	 */
	public static function getByteStream(): ByteStream
	{
		$intLen = count(self::$arrByteStreamsFree);

		if($intLen === 0)
		{
			self::$arrByteStreamsFree[] = new ByteStream();
		}

		$objStream                  = array_shift(self::$arrByteStreamsFree);
		self::$arrByteStreamsUsed[] = $objStream;

		return $objStream;
	}


	/**
	 * @param ByteStream $objStream
	 */
	public static function freeByteStream(ByteStream $objStream)
	{
		$intKeyIndex = array_search($objStream, self::$arrByteStreamsUsed, true);

		if($intKeyIndex === false) return;

		array_splice(self::$arrByteStreamsUsed, $intKeyIndex, 1);

		$objStream->reset();
		self::$arrByteStreamsFree[] = $objStream;
	}
}