<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\utils\debugging;


class DebugLevel
{
	const ALL	= 0b111111;
	const TRACE	= 1;
	const DEBUG	= 1 << 1;
	const INFO	= 1 << 2;
	const WARN	= 1 << 3;
	const ERROR	= 1 << 4;
	const FATAL	= 1 << 5;
}