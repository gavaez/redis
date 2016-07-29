<?php

namespace Redis\Exception;

/**
 * Redis connect exception
 */
class ConnectException extends ClientException
{
    /** @noinspection PhpDocSignatureInspection */

    /**
     * @param string     $host
     * @param null|int   $port
     * @param int        $code     [optional]
     * @param int        $severity [optional]
     * @param string     $filename [optional]
     * @param int        $lineno   [optional]
     * @param \Exception $previous [optional]
     */
    public function __construct(
        $host,
        $port,
        $code = 0,
        $severity = 1,
        $filename = __FILE__,
        $lineno = __LINE__,
        \Exception $previous = null
    ) {
        parent::__construct(
            sprintf('Can\'t connect to redis host="%s", port="%u"', $host, $port),
            $code,
            $severity,
            $filename,
            $lineno,
            $previous
        );
    }
}
