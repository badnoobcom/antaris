<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace com\badnoob\antaris\io\ajp13;


use com\badnoob\antaris\io\http\request\HttpConstants;

class AJP13Constants
{
	 // Prefix codes for message types from server to container
    const JK_AJP13_FORWARD_REQUEST   = 2;
    const JK_AJP13_SHUTDOWN          = 7;    // XXX Unused
    const JK_AJP13_PING_REQUEST      = 8;    // XXX Unused
    const JK_AJP13_CPING_REQUEST     = 10;

    // Prefix codes for message types from container to server
    const JK_AJP13_SEND_BODY_CHUNK   = 3;
    const JK_AJP13_SEND_HEADERS      = 4;
    const JK_AJP13_END_RESPONSE      = 5;
    const JK_AJP13_GET_BODY_CHUNK    = 6;
    const JK_AJP13_CPONG_REPLY       = 9;

    const SC_REQ_ACCEPT          = 0xA001;
	const SC_REQ_ACCEPT_CHARSET  = 0xA002;
	const SC_REQ_ACCEPT_ENCODING = 0xA003;
	const SC_REQ_ACCEPT_LANGUAGE = 0xA004;
	const SC_REQ_AUTHORIZATION   = 0xA005;
	const SC_REQ_CONNECTION      = 0xA006;
	const SC_REQ_CONTENT_LENGTH  = 0xA008;
	const SC_REQ_CONTENT_TYPE    = 0xA007;
	const SC_REQ_COOKIE          = 0xA009;
	const SC_REQ_COOKIE2         = 0xA00A; // I wonder what this might be ^^
	const SC_REQ_HOST            = 0xA00B;
	const SC_REQ_PRAGMA          = 0xA00C;
	const SC_REQ_REFERER         = 0xA00D;
	const SC_REQ_USER_AGENT      = 0xA00E;

	/**
	 * Specifies the boundary when the type is used as string length;
	 */
	const STRING_LENGTH_BOUNDARY = 0xA000;

	/**
	 * A mapping for easy translation from request code (integer) to request name (string).
	 * This is a variable instead of a const, because otherwise IDEs/editors show errors when
	 * accessing keys.
	 * Should not make that much of a difference, though.
	 *
	 * @var array
	 */
	public static $HEADER_NAME_MAP = [
		self::SC_REQ_ACCEPT          => 'accept',
		self::SC_REQ_ACCEPT_CHARSET  => 'accept-charset',
		self::SC_REQ_ACCEPT_ENCODING => 'accept-encoding',
		self::SC_REQ_ACCEPT_LANGUAGE => 'accept-language',
		self::SC_REQ_AUTHORIZATION   => 'authorization',
		self::SC_REQ_CONNECTION      => 'connection',
		self::SC_REQ_CONTENT_LENGTH  => 'content-length',
		self::SC_REQ_CONTENT_TYPE    => 'content-type',
		self::SC_REQ_COOKIE          => 'cookie',
		self::SC_REQ_COOKIE2         => 'cookie2',
		self::SC_REQ_HOST            => 'host',
		self::SC_REQ_PRAGMA          => 'pragma',
		self::SC_REQ_REFERER         => 'referer',
		self::SC_REQ_USER_AGENT      => 'user-agent'
	];

	// Integer codes for common response header strings
	const SC_RESP_CONTENT_TYPE     = 0xA001;
	const SC_RESP_CONTENT_LANGUAGE = 0xA002;
	const SC_RESP_CONTENT_LENGTH   = 0xA003;
	const SC_RESP_DATE             = 0xA004;
	const SC_RESP_LAST_MODIFIED    = 0xA005;
	const SC_RESP_LOCATION         = 0xA006;
	const SC_RESP_SET_COOKIE       = 0xA007;
	const SC_RESP_SET_COOKIE2      = 0xA008;
	const SC_RESP_SERVLET_ENGINE   = 0xA009;
	const SC_RESP_STATUS           = 0xA00A;
	const SC_RESP_WWW_AUTHENTICATE = 0xA00B;
	const RESP_HEADER_NAME_MAP     = [
										self::SC_RESP_CONTENT_LANGUAGE	=>	'Content-Language',
										self::SC_RESP_CONTENT_LENGTH		=>	'Content-Length',
										self::SC_RESP_CONTENT_TYPE		=>	'Content-Type',
										self::SC_RESP_DATE				=>	'Date',
										self::SC_RESP_LAST_MODIFIED		=>	'Last-Modified',
										self::SC_RESP_LOCATION			=>	'Location',
										self::SC_RESP_SERVLET_ENGINE		=>	'Servlet-Engine',
										self::SC_RESP_SET_COOKIE			=>	'Set-Cookie',
										self::SC_RESP_SET_COOKIE2		=>	'Set-Cookie2',
										self::SC_RESP_STATUS				=>	'Status',
										self::SC_RESP_WWW_AUTHENTICATE	=>	'WWW-Authenticate'
									  ];
	const RESP_CUSTOM = -1;

    // Integer codes for common (optional) request attribute names
    const SC_A_CONTEXT       = 1;  // XXX Unused
    const SC_A_SERVLET_PATH  = 2;  // XXX Unused
    const SC_A_REMOTE_USER   = 3;
    const SC_A_AUTH_TYPE     = 4;
    const SC_A_QUERY_STRING  = 5;
    const SC_A_JVM_ROUTE     = 6;
    const SC_A_SSL_CERT      = 7;
    const SC_A_SSL_CIPHER    = 8;
    const SC_A_SSL_SESSION   = 9;
    const SC_A_SSL_KEY_SIZE  = 11;
    const SC_A_SECRET        = 12;
    const SC_A_STORED_METHOD = 13;

    // Used for attributes which are not in the list above
    const SC_A_REQ_ATTRIBUTE = 10;

    /**
     * AJP private request attributes
     */
    const SC_A_REQ_LOCAL_ADDR  = 'AJP_LOCAL_ADDR';
    const SC_A_REQ_REMOTE_PORT = 'AJP_REMOTE_PORT';
    const SC_A_SSL_PROTOCOL    = 'AJP_SSL_PROTOCOL';

	/**
     * Size of basic packet header
     */
    const HEADER_SIZE = 4;

	/**
     * Default maximum total byte size for a AJP packet
     */
    const MAX_PACKET_SIZE = 8192;

	/**
     * Size of the header metadata
     */
    const READ_HEAD_LEN = 6;
    const SEND_HEAD_LEN = 7;

    /**
     * Default maximum size of data that can be sent in one packet
     */
    const MAX_READ_SIZE = self::MAX_PACKET_SIZE - self::READ_HEAD_LEN;
	/**
	 * The maximum length that a body message can have. 8K - AB(packet length 2 bytes) - messageType - data length 2 bytes
	 */
    const MAX_SEND_SIZE = self::MAX_PACKET_SIZE - self::SEND_HEAD_LEN;

	const METHOD_TYPE_TO_METHOD_NAME = [
											AJP13Message::HTTP_METHOD_GET		=> HttpConstants::METHOD_NAME_GET,
											AJP13Message::HTTP_METHOD_HEAD		=> HttpConstants::METHOD_NAME_HEAD,
											AJP13Message::HTTP_METHOD_OPTIONS	=> HttpConstants::METHOD_NAME_OPTIONS,
											AJP13Message::HTTP_METHOD_POST		=> HttpConstants::METHOD_NAME_POST
									   ];
}
