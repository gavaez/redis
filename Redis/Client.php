<?php

namespace Redis;

use Redis\Exception\ClientException;
use Redis\Exception\ConnectException;
use Redis\Exception\LockAcquireTimeoutException;

/**
 * Redis client
 */
class Client extends \Redis
{
    const DEFAULT_EXPIRE = 14400; // 4 hours
    const MAX_EXPIRE     = 2678400; // 31 days

    /**
     * @param string $host
     * @param int    $port    [optional]
     * @param int    $timeout [optional]
     *
     * @return bool
     * @throws ConnectException
     */
    public function connect($host, $port = 6379, $timeout = 0)
    {
        $success = parent::connect($host, $port, $timeout);
        if (!$success) {
            throw new ConnectException($host, $port);
        }

        return $success;
    }

    /**
     * @see setex
     *
     * @param string $name
     * @param string $value
     * @param int    $expire
     *
     * @return bool
     * @throws ClientException
     */
    public function set($name, $value, $expire = self::DEFAULT_EXPIRE)
    {
        if (self::MAX_EXPIRE < $expire) {
            throw new ClientException(sprintf('You can specify expire period not more %u seconds', self::MAX_EXPIRE));
        }

        return $this->setex($name, $expire, $value);
    }

    /**
     * @param int    $dbindex [optional]
     * @param string $prefix  [optional]
     *
     * @throws ClientException
     */
    public function initNs($dbindex = null, $prefix = null)
    {
        if ($dbindex) {
            $this->select($dbindex);
        }

        $this->setOption(self::OPT_SERIALIZER, self::SERIALIZER_PHP);

        if ($prefix) {
            $this->setOption(self::OPT_PREFIX, 	$prefix);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws ClientException
     */
    public function select($dbindex)
    {
        $success = parent::select($dbindex);
        if (!$success) {
            throw new ClientException('Can\'t select redis database #' . $dbindex);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ClientException
     */
    public function setOption($name, $value)
    {
        $success = parent::setOption($name, $value);
        if (!$success) {
            throw new ClientException(sprintf('Can\'t set option "%s" to "%s"', $name, $value));
        }

        return $success;
    }

    /**
     * @param string $key
     * @param int    $expire  [optional]
     * @param int    $timeout [optional]
     * @param int    $delay   [optional]
     *
     * @return bool
     * @throws LockAcquireTimeoutException
     */
    public function acquireLock($key, $expire = 30, $timeout = 35, $delay = 10000)
    {
        $limit = time() + $timeout;

        while (time() < $limit)
        {
            if ($this->setnx($key, time() + $expire)) {
                $this->expire($key, $expire);
                return true;
            }

            usleep($delay);
        }

        throw new LockAcquireTimeoutException($timeout);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function releaseLock($key)
    {
        return (bool) $this->delete($key);
    }
}
