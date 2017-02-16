<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\exceptions;


class ServletException extends \Exception
{
	const GENERIC_EXCEPTION		= 'A generic ServletException occurred.';
	const INCOMPATIBLE_SERVLET	= 'An exception occurred while injecting request and response into the Servlet. Make'
								  .' sure the Servlet uses the "Servlet" trait and implements the "IServlet"'
								  .'interface.';
	const RUNTIME_EXCEPTION		= 'A runtime exception occurred while running the servlet.';
}