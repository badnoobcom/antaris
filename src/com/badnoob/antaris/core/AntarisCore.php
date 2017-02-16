<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\core;


use com\badnoob\antaris\io\socketserver\SocketServer;

class AntarisCore
{
	private $objSocketServer;


	public function __construct()
	{
		$this->objSocketServer = new SocketServer();
		$this->objSocketServer->run();
	}
}