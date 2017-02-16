<?php


namespace com\badnoob\antaris\exceptions;


class NullPointerException extends \Exception
{
	public function __construct($message, $code = null, \Exception $previous = null)
	{
		parent::__construct($message,
							$code,
							$previous);
	}
}