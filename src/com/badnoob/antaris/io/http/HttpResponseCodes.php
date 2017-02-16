<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\http;


class HttpResponseCodes
{
	const	CODE_200_OK						=	200;

	const	CODE_403_FORBIDDEN				=	403;
	const	CODE_404_NOT_FOUND				=	404;

	const	CODE_503_INTERNAL_SERVER_ERROR	= 	503;
}