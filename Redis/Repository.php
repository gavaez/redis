<?php

namespace Redis;

/**
 * Base redis repository service
 *
 * Данные из этого класса получать через get<ИМЯ_КЛЮЧА>.
 *
 * Для каждого ключа нужно написать функцию-генератор данных с именем calc<ИМЯ_КЛЮЧА>,
 * в скобках можно указать параметры от которых зависит ключ.
 *
 * Также можно вызывать функцию regenerate<ИМЯ_КЛЮЧА> чтобы перегенерировать данные,
 * например при добавлении статьи кэш последних статей становится невалидным и нужно его перегенерировать.
 *
 * Если значение ключа зависит от входных параметров, необходимо сделать функцию warmup<ИМЯ_КЛЮЧА>,
 * которая сгенерировала бы значения для всех возможных параметров.
 *
 * Чтобы удалить какой-то ключ нужно вызывать invalidate<ИМЯ_КЛЮЧА>,
 * в скобках можно указать параметры от которых зависит ключ.
 */
abstract class Repository
{
    const KEY_PREFIX = 'redisRepo';

    /**
     * @var Client
     */
    protected $redis;

    /**
     * @param Client $redis
     */
    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @throws \ErrorException
     */
    public function warmup()
    {
        $methods = get_class_methods($this);

        foreach ($methods as $method) {
            if (!preg_match('#^calc(\w+)$#', $method, $matches)) {
                continue;
            }

            list(, $key) = $matches;
            if (in_array($warmup = 'warmup' . $key, $methods)) {
                $this->$warmup();
                continue;
            }

            if ($paramsCount = (new \ReflectionMethod($this, $method))->getNumberOfRequiredParameters()) {
                throw new \ErrorException(
                    "Задан calc-метод для вычисления ключей '$key', завясящих от $paramsCount обязательных параметров, "
                        . "но нет функции $method, которая бы сгенерировала значения "
                        . 'для всех возможных входных параметров'
                );
            }

            $this->regenerate($key);
        }
    }

    /**
     * @param string $key
     * @param array  $arguments
     *
     * @return mixed
     * @throws \ErrorException
     */
    public function regenerate($key, array $arguments = [])
    {
        $method = $this->getCalcMethod($key);

        $ret = call_user_func_array([$this, $method], $arguments);
        if ($ret === false) {
            throw new \ErrorException("Не удалось выполнить метод '$method', либо он вернул некорретный результат");
        }

        $this->redis->set($this->getKeyName($key, $arguments), $ret);

        return $ret;
    }

    /**
     * @param string $key
     * @param array  $arguments
     *
     * @throws \ErrorException
     */
    public function invalidate($key, array $arguments = [])
    {
        $this->getCalcMethod($key);

        $this->redis->delete($this->getKeyName($key, $arguments));
    }

    /**
     * PHP magic caller
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     * @throws \ErrorException
     */
    public function __call($name, array $arguments)
    {
        static $methods = ['get', 'regenerate', 'invalidate'];

        if (preg_match(sprintf('#^(%s)(\w+)$#i', implode('|', $methods)), $name, $matches)) {
            list(, $method, $name) = $matches;

            return $this->{mb_strtolower($method)}($name, $arguments);
        }

        throw new \ErrorException(
            "Вызвана неизвестная функция '$name'. Префикс имени функции может быть только один из следующих: "
                . implode(', ', $methods)
        );
    }

    /**
     * @param mixed $arg
     *
     * @return string
     */
    abstract protected function getKeyPart($arg);

    /**
     * @param string $key
     * @param array  $arguments
     *
     * @return string
     */
    protected function getKeyName($key, array $arguments = [])
    {
        $key = static::KEY_PREFIX . ':' . $key;

        if ($arguments) {
            $key .= ':' . md5(serialize(array_map([$this, 'getKeyPart'], $arguments)));
        }

        return $key;
    }

    /**
     * @param string $key
     *
     * @return string
     * @throws \ErrorException
     */
    protected function getCalcMethod($key)
    {
        if (method_exists($this, $method = 'calc' . $key)) {
            throw new \ErrorException(
                "Не создан метод '$method' для генерации кэша. Возможно допущена ошибка в названии ключа."
            );
        }

        return $method;
    }

    /**
     * @param string $key
     * @param array  $arguments [optional]
     *
     * @return bool|null|string
     */
    protected function get($key, array $arguments = [])
    {
        $this->getCalcMethod($key);

        return $this->redis->getStored(
            $this->getKeyName($key, $arguments), [$this, 'regenerate'], Client::DEFAULT_EXPIRE, [$key, $arguments]
        );
    }
}
