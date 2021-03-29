<?php

declare(strict_types=1);

namespace Berlioz\ServiceContainer\Container;

use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Instantiator;
use Psr\Container\ContainerInterface;

/**
 * Class AutoWiringContainer.
 */
class AutoWiringContainer implements ContainerInterface
{
    protected Instantiator $instantiator;
    protected array $cache = [];

    public function __construct(?Instantiator $instantiator = null)
    {
        $this->instantiator = $instantiator ?? new Instantiator($this);
    }

    /**
     * @inheritDoc
     */
    public function get($id): object
    {
        if (!class_exists($id)) {
            throw NotFoundException::classDoesNotExists($id);
        }

        // Exists in cache
        if (array_key_exists($id, $this->cache)) {
            if (false !== ($object = reset($this->cache[$id]))) {
                return $object;
            }
        }

        try {
            $object = $this->instantiator->newInstanceOf($id);

            if (false !== ($implements = class_implements($object))) {
                array_unshift($implements, get_class($object));
                array_walk($implements, fn(string $implement) => $this->cache[$implement][] = $object);
            }

            return $object;
        } catch (ContainerException $exception) {
            throw ContainerException::instantiation($id, $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function has($id): bool
    {
        if (array_key_exists($id, $this->cache)) {
            return true;
        }

        return class_exists($id);
    }
}