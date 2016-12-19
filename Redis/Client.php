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
    const DEFAULT_EXPIRE = 14400;   // 4 hours
    const MAX_EXPIRE     = 2678400; // 31 days

    /**
     * @param string $host
     * @param int    $port          [optional]
     * @param int    $timeout       [optional]
     * @param int    $retryInterval [optional]
     *
     * @return bool
     * @throws ConnectException
     */
    public function connect($host, $port = 6379, $timeout = 0, $retryInterval = 0)
    {
        $success = parent::connect($host, $port, $timeout, $retryInterval);
        if (!$success) {
            throw new ConnectException($host, $port);
        }

        return $success;
    }

    /**
     * @see setex
     *
     * @param string $key
     * @param string $value
     * @param int    $expire [optional]
     *
     * @return bool
     * @throws ClientException
     */
    public function set($key, $value, $expire = self::DEFAULT_EXPIRE)
    {
        if (self::MAX_EXPIRE < $expire) {
            throw new ClientException(sprintf('You can specify expire period not more %u seconds', self::MAX_EXPIRE));
        }

        return $this->setex($key, $expire, $value);
    }

    /**
     * @param string   $key
     * @param callable $callback [optional]
     * @param int      $expire   [optional]
     * @param array    $args     [optional]
     *
     * @return bool|null|string
     */
    public function getStored($key, callable $callback = null, $expire = self::DEFAULT_EXPIRE, array $args = [])
    {
        $value = $this->get($key);

        if ($value === false) {
            $this->set($key, $value = is_callable($callback) ? $callback($key) : null, $expire);
        }

        return $value;
    }

    /**
     * @param int    $dbindex [optional]
     * @param string $prefix  [optional]
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

        while (time() < $limit) {
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
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        return (bool) $this->delete($key);
    }
}
