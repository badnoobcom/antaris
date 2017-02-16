<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\ajp13\response;

use com\badnoob\antaris\core\datatypes\StringWrapper;

class AJP13ResponseTest extends \PHPUnit_Framework_TestCase
{
	public function testCorrectLengthForEmptyMessage()
	{
		$objResponse	= new AJP13Response();
		$objStream		= $objResponse->getStream();
		$objStream->setPosition(2);
		$intLength = $objStream->readUnsignedShort();

		$this->assertEquals(0, $intLength);
	}


	public function testCorrectLengthAfterRead()
	{
		$objResponse = new AJP13Response();
		$strResponse = $objResponse->getAsString();
		$intStringLength = $strResponse->getLength();

		$this->assertEquals(4, $intStringLength);
	}


	public function testWriteBodyChunk()
	{
		$strResponse = new StringWrapper('');
		$objResponse = new AJP13Response();
		$strMessage = new StringWrapper('HelloWorld');

		$objResponse->recycle();
		$objResponse->writeBodyChunk($strMessage);
		$strResponse->append($objResponse->getAsString());

		$intAssertLength = 7 + $strMessage->getLength();
		$intActualLength = $strResponse->getLength();
		$this->assertEquals($intAssertLength, $intActualLength);
	}
}
