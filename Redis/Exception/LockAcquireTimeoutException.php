<?php

namespace Redis\Exception;

/**
 * Redis lick timeout exception
 */
class LockAcquireTimeoutException extends ClientException
{
    /**
     * @param int        $timeout
     * @param int        $code     [optional]
     * @param int        $severity [optional]
     * @param string     $filename [optional]
     * @param int        $lineno   [optional]
     * @param \Exception $previous [optional]
     */
    public function __construct
        ($timeout, $code = 0, $severity = 1, $filename = __FILE__, $lineno = __LINE__, \Exception $previous = null)
    {
        parent::__construct(
            sprintf('Lock failed: more than %u seconds', $timeout),
            $code,
            $severity,
            $filename,
            $lineno,
            $previous
        );
    }
}
