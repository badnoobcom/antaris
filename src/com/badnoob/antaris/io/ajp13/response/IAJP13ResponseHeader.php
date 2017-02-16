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


interface IAJP13ResponseHeader
{
	public function __destruct();

	public function getHeaderName():string;

	public function getHeaderValue():string;

	public function getType():int;
}