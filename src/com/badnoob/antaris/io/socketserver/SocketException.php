<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\socketserver;


class SocketException extends \Exception
{

	/**
	 * SocketException constructor.
	 *
	 * @param string $strErrorMessage
	 */
	public function __construct(string $strErrorMessage)
	{
		parent::__construct($strErrorMessage);
	}
}