<?php


namespace com\badnoob\antaris\io\http\request;


class HttpFormDataElement
{
	const TYPE_FILE = 1;

	const TYPE_TEXT = 2;
	const TYPE_TEXT_PLAIN = 'text/plain';

	private $intType;

	private $strFileName;

	private $strLabel;

	private $intSize;

	private $strType;

	private $strBody;


	public function getBody():string { return $this->strBody; }
	public function setBody(string $body) { $this->strBody = $body; }

	public function getType():string { return $this->strType; }
	public function setType(string $type) { $this->strType = $type; }

	public function getSize():int { return $this->intSize; }
	public function setSize(int $size) { $this->intSize = $size; }

	public function getLabel():string { return $this->strLabel; }
	public function setLabel(string $label) { $this->strLabel = $label; }

	public function getFileName():string { return $this->strFileName; }
	public function setFileName(string $filename) { $this->strFileName = $filename; }

	public function __debugInfo():array
	{
		return [$this->__toString()];
	}


	public function __toString():string
	{
		$strValue = 'HttpFormDataElement('
					.'Label="'. $this->strLabel .'", '
					.'Size='. $this->intSize .', '
					.'Type="'. $this->strType .'"';

		if($this->strType !== self::TYPE_TEXT_PLAIN)
		{
			$strValue .= ', '
			 	. 'Filename="' . $this->strFileName . '", '
				. 'Body="binary"';
		}

		$strValue .= ')';

		return $strValue;
	}
}