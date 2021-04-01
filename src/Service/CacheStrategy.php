<?php

declare(strict_types=1);

namespace Berlioz\ServiceContainer\Service;

use Berlioz\ServiceContainer\Exception\ContainerException;
use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class CacheStrategy.
 */
class CacheStrategy
{
    const CACHE_PATTERN = 'CONTAINER_%s';

    public function __construct(
        protected CacheInterface $cache,
        protected DateInterval|int|null $ttl = null,
    ) {
    }

    protected function getCacheKey(Service $service): string
    {
        return sprintf(static::CACHE_PATTERN, $service->getAlias());
    }

    /**
     * Get cache.
     *
     * @param Service $service
     *
     * @return object|null
     * @throws ContainerException
     */
    public function get(Service $service): ?object
    {
        try {
            if (!$this->cache->has($this->getCacheKey($service))) {
                return null;
            }

            $object = $this->cache->get($this->getCacheKey($service));

            if (!is_a($object, $service->getClass())) {
                throw new ContainerException(sprintf('Cache integrity of service "%s"', $service->getAlias()));
            }

            return $object;
        } catch (InvalidArgumentException $exception) {
            throw new ContainerException(
                sprintf('Error during cache read of service "%s"', $service->getAlias()),
                exception: $exception
            );
        }
    }

    /**
     * Set cache.
     *
     * @param Service $service
     * @param object $object
     *
     * @return bool
     * @throws ContainerException
     */
    public function set(Service $service, object $object): bool
    {
        try {
            return $this->cache->set($this->getCacheKey($service), $object, $this->ttl);
        } catch (InvalidArgumentException $exception) {
            throw new ContainerException('Error during cache write', 0, $exception);
        }
    }
}