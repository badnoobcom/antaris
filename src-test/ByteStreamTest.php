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

use com\badnoob\antaris\io\streams\ByteStream;

class ByteStreamTest extends \PHPUnit_Framework_TestCase
{
	public function testWriteCorrectString()
	{
		$objStream = new ByteStream();
		$objStream->writeString('HWorld');
		$objStream->setPosition(1);
		$objStream->writeString('XX');

		$objStream->setPosition(0);
		$strValue = $objStream->readAll();

		$this->assertEquals('HXXrld', $strValue);
	}


	public function testAppendStringCorrectly()
	{
		$objStream = new ByteStream();
		$objStream->writeString('012345');
		$objStream->setPosition(4);
		$objStream->writeString('XX');

		$objStream->setPosition(0);
		$strValue = $objStream->readAll();

		$this->assertEquals('0123XX', $strValue);
	}


	public function testCorrectPositionAfterWriteByte()
	{
		$objStream = new ByteStream();
		$objStream->writeByte(0);

		$intPosition = $objStream->getPosition();

		$this->assertEquals(1, $intPosition);
	}


	public function testCorrectPositionAfterWriteUnsignedByte()
	{
		$objStream = new ByteStream();
		$objStream->writeUnsignedByte(0);

		$intPosition = $objStream->getPosition();

		$this->assertEquals(1, $intPosition);
	}


	public function testCorrectPositionAfterWriteShort()
	{
		$objStream = new ByteStream();
		$objStream->writeShort(-355);

		$intPosition = $objStream->getPosition();

		$this->assertEquals(2, $intPosition);
	}


	public function testCorrectPositionAfterWriteUnsignedShort()
	{
		$objStream = new ByteStream();
		$objStream->writeUnsignedShort(-355);

		$intPosition = $objStream->getPosition();

		$this->assertEquals(2, $intPosition);
	}


	public function testCorrectPositionAfterWriteLengthEncodedString()
	{
		$objStream = new ByteStream();
		$objStream->writeStringLengthEncodedNullTerminated('HelloWorld');

		$intPosition = $objStream->getPosition();

		$this->assertEquals(13, $intPosition);
	}

	public function testReadShortReturnsCorrectValues()
	{
		$objStream = new ByteStream();
		$objStream->setEndian(ByteStream::BIG_ENDIAN);
		$objStream->writeByte(-1);
		$objStream->writeByte(-1);
		$objStream->setPosition(0);

		$this->assertEquals(-1, $objStream->readShort(), 'readShort returns -1');

		$objStream[0] = 0;
		$objStream->setPosition(0);
		$this->assertEquals(255, $objStream->readShort(), 'readShort returns 255');
	}


	public function testReadUnsignedShortHasCorrectValueForBigEndian()
	{
		$objStream = new ByteStream();
		$objStream[$objStream->getPosition()] = 0xFF;
		$objStream->setPosition($objStream->getPosition() + 1);
		$objStream[$objStream->getPosition()] = 0xA0;

		$objStream->setPosition(0);
		$intUnsignedShort = $objStream->readUnsignedShort();

		$this->assertEquals(0xFFA0, $intUnsignedShort);
	}


	public function testReadUnsignedShortHasCorrectValueForLittleEndian()
	{
		$objStream = new ByteStream();
		$objStream->setEndian(ByteStream::LITTLE_ENDIAN);
		$objStream[$objStream->getPosition()] = 0xFF;
		$objStream->setPosition($objStream->getPosition() + 1);
		$objStream[$objStream->getPosition()] = 0xA0;

		$objStream->setPosition(0);
		$intUnsignedShort = $objStream->readUnsignedShort();

		$this->assertEquals(0xA0FF, $intUnsignedShort);
	}


	public function testWriteUnsignedShortHasCorrectValueForBigEndian()
	{
		$objStream = new ByteStream();
		$objStream->writeUnsignedShort(0xFFA0);

		$objStream->setPosition(0);
		$intUnsignedShort = $objStream->readUnsignedShort();

		$this->assertEquals(0xFFA0, $intUnsignedShort);
	}


	public function testWriteUnsignedShortHasCorrectValueForLittleEndian()
	{
		$objStream = new ByteStream();
		$objStream->setEndian(ByteStream::LITTLE_ENDIAN);
		$objStream->writeUnsignedShort(0xFFA0);

		$objStream->setPosition(0);
		$intUnsignedShort = $objStream->readUnsignedShort();

		$this->assertEquals(0xFFA0, $intUnsignedShort);
	}


	public function testSetUnsignedByteHasCorrectValue()
	{
		$objStream = new ByteStream();
		$objStream[0] = 56;
		$objStream->setPosition(0);

		$bytUnsignedByte = $objStream->readUnsignedByte();
		$this->assertEquals(56, $bytUnsignedByte);

		$bytUnsignedByte = unpack('C*', $objStream[0])[1];
		$this->assertEquals(56, $bytUnsignedByte);
	}


	public function testSetSignedByteHasCorrectValue()
	{
		$objStream = new ByteStream();
		$objStream[0] = -56;
		$objStream->setPosition(0);

		$bytSignedByte = $objStream->readByte();
		$this->assertEquals(-56, $bytSignedByte);

		$bytSignedByte = unpack('c*', $objStream[0])[1];
		$this->assertEquals(-56, $bytSignedByte);
	}
}
