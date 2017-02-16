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


class SocketServerConstants
{

	const IP = '127.0.0.1';

	const PORT = 8009;

	const SOCKET_SELECT_TIMEOUT = 500;

	/**
	 * The number of retries to perform, until we receive the first data packet.
	 */
	const EMPTY_BUFFER_RETRIES = 50;
}