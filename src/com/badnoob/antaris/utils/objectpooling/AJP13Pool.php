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


use com\badnoob\antaris\io\ajp13\AJP13Message;
use com\badnoob\antaris\io\ajp13\response\AJP13Response;

class AJP13Pool
{

	/**
	 * @var AJP13Message[]
	 */
	private static $arrAJP13MessagesFree = [];

	/**
	 * @var AJP13Message[];
	 */
	private static $arrAJP13MessagesUsed = [];

	/**
	 * @var AJP13Response[]
	 */
	private static $arrAJP13ResponsesFree = [];

	/**
	 * @var AJP13Response[]
	 */
	private static $arrAJP13ResponsesUsed = [];


	/**
	 * @return AJP13Message
	 */
	public static function getAJP13Message():AJP13Message
	{
		$intLen = count(self::$arrAJP13MessagesFree);

		if ($intLen === 0)
		{
			self::$arrAJP13MessagesFree[] = new AJP13Message();
		}

		$objMessage = array_shift(self::$arrAJP13MessagesFree);
		self::$arrAJP13MessagesUsed[] = $objMessage;

		return $objMessage;
	}


	/**
	 * @param AJP13Message $objMessage
	 */
	public static function freeAJP13Message(AJP13Message $objMessage)
	{
		$intKeyIndex = array_search($objMessage, self::$arrAJP13MessagesUsed, true);
		if ($intKeyIndex === false) return;

		array_splice(self::$arrAJP13MessagesUsed, $intKeyIndex, 1);

		$objMessage->reset();
		self::$arrAJP13MessagesFree[] = $objMessage;
	}


	/**
	 * @param AJP13Response $objResponse
	 */
	public static function freeAJP13Response(AJP13Response $objResponse)
	{
		$intKeyIndex = array_search($objResponse, self::$arrAJP13ResponsesUsed, true);
		if ($intKeyIndex === false) return;

		array_splice(self::$arrAJP13ResponsesUsed, $intKeyIndex, 1);

		$objResponse->recycle();
		self::$arrAJP13ResponsesFree[] = $objResponse;
	}


	/**
	 * @return AJP13Response
	 */
	public static function getAJP13Response():AJP13Response
	{
		$intLen = count(self::$arrAJP13ResponsesFree);

		if ($intLen === 0)
		{
			self::$arrAJP13ResponsesFree[] = new AJP13Response();
		}

		$objMessage = array_shift(self::$arrAJP13ResponsesFree);
		self::$arrAJP13ResponsesUsed[] = $objMessage;

		return $objMessage;
	}
}