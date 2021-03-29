<?php
declare(strict_types=1);

namespace Berlioz\ServiceContainer\Tests\Service;

use Psr\SimpleCache\CacheInterface;

/**
 * Class MemoryCacheDriver.
 *
 * @package Berlioz\Core\Cache
 */
class MemoryCacheDriver implements CacheInterface
{
    protected array $data = [];

    /**
     * @inheritDoc
     */
    public function has($key): bool
    {
        if (!array_key_exists($key, $this->data)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            return $this->data[$key]['value'];
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null): bool
    {
        $this->data[$key] = [
            'ttl' => $ttl,
            'value' => $value,
        ];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete($key): bool
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);

            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $this->data = [];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        // TODO: Implement getMultiple() method.
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        // TODO: Implement setMultiple() method.
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        // TODO: Implement deleteMultiple() method.
    }
}