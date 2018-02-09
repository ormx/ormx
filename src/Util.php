<?php
/**
 * Created by PhpStorm.
 * User: Xav
 * Date: 27-Mar-17
 * Time: 13:42
 */

namespace OrmX;


class Util
{

    /**
     * Returns ASCII encoded text as UTF-8.
     * This is necessary for 10-4 which returns a request body with BOM and unicode quotation marks.
     * @param $ascii
     * @return mixed
     */
    public static $codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];


    public static function utf8Encode($ascii)
    {
        $binary = \pack('H*', 'EFBBBF');

        return \preg_replace("/^$binary/", '', $ascii);
    }

    /**
     * Util for returning a code if it's valid http code otherwise return a 500
     * this is for use in exception handling where if we get a DB exception you will get weird codes so you
     * can't just use the excpetion code.
     *
     * @param $code
     * @return int
     */
    public static function httpStatus($code)
    {
        return isset(self::$codes[$code]) ? $code : 500;
    }

    public static function psr2VariableName($value)
    {
        //set the first charater to lowercase
        return \strtolower($value[0]) . \substr($value, 1);
    }

    public static function makeSetter($variable)
    {
        return 'set' . \strtoupper($variable[0]) . \substr($variable, 1);
    }

    /**
     * @param $variable
     * @return string
     */
    public static function makeGetter($variable)
    {
        return 'get' . \strtoupper($variable[0]) . \substr($variable, 1);
    }

}