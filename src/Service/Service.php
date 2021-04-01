<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\ServiceContainer\Service;

use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Instantiator;

/**
 * Class Service.
 */
class Service
{
    protected string $class;
    protected mixed $factory = null;
    protected array $arguments = [];
    protected array $calls = [];
    protected ?object $object = null;
    protected bool $initialization = false;

    /**
     * Service constructor.
     *
     * @param string|object $class
     * @param null|string $alias
     * @param callable|string|null $factory
     * @param CacheStrategy|null $cacheStrategy
     */
    public function __construct(
        string|object $class,
        protected ?string $alias = null,
        callable|string|null $factory = null,
        protected ?CacheStrategy $cacheStrategy = null,
    ) {
        // Get class name of object
        if (is_object($class)) {
            $this->object = $class;
            $class = get_class($class);
            $this->initialization = true;
        }

        $this->class = $class;
        $this->factory = $factory;
    }

    /**
     * PHP serialize method.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'class' => $this->class,
            'factory' => $this->factory,
            'alias' => $this->alias,
            'arguments' => $this->arguments,
            'calls' => $this->calls
        ];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     *
     * @throws ContainerException
     */
    public function __unserialize(array $data): void
    {
        $this->class = $data['class'] ?? throw new ContainerException('Serialization error');
        $this->factory = $data['factory'] ?? throw new ContainerException('Serialization error');
        $this->alias = $data['alias'] ?? throw new ContainerException('Serialization error');
        $this->arguments = $data['arguments'] ?? throw new ContainerException('Serialization error');
        $this->calls = $data['calls'] ?? throw new ContainerException('Serialization error');
    }

    /**
     * Get class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Get alias.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias ?? $this->class;
    }

    /**
     * Get arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments ?? [];
    }

    /**
     * Add argument.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return static
     */
    public function addArgument(string $name, mixed $value): static
    {
        $this->arguments[$name] = $value;

        return $this;
    }

    /**
     * Add arguments.
     *
     * @param mixed[] $arguments
     *
     * @return static
     */
    public function addArguments(array $arguments): static
    {
        $this->arguments = array_replace($this->arguments, $arguments);

        return $this;
    }

    /**
     * Add call.
     *
     * @param string $method
     * @param mixed[] $arguments
     *
     * @return static
     */
    public function addCall(string $method, array $arguments = []): static
    {
        $this->calls[] = [$method, $arguments];

        return $this;
    }

    /**
     * Add calls.
     *
     * @param array $calls
     *
     * @return static
     */
    public function addCalls(array $calls): static
    {
        foreach ($calls as $method => $arguments) {
            $this->addCall($method, $arguments);
        }

        return $this;
    }

    /**
     * Get factory.
     *
     * @return mixed
     */
    public function getFactory(): mixed
    {
        return $this->factory;
    }

    /**
     * Set factory
     *
     * @param callable|string|null $factory
     *
     * @return static
     */
    public function setFactory(callable|string|null $factory): static
    {
        $this->factory = $factory;

        return $this;
    }

    /**
     * Get service.
     *
     * @param Instantiator $instantiator
     *
     * @return object
     * @throws ContainerException
     */
    public function get(Instantiator $instantiator): object
    {
        // Already initialized?
        if (null !== $this->object) {
            return $this->object;
        }

        // Already in initialization?
        if ($this->initialization) {
            throw new ContainerException(sprintf('Recursive initialization of service "%s"', $this->class));
        }
        $this->initialization = true;

        // Get from cache
        if (null !== ($object = $this->cacheStrategy?->get($this))) {
            $this->object = $object;
            $this->calls($this->object, $instantiator);

            return $object;
        }

        // Factory?
        if (null !== $this->factory) {
            $object = $this->factory($instantiator);

            // NULL result of factory
            if (null !== $object) {
                if (!is_object($object) || !$object instanceof $this->class) {
                    throw ContainerException::exceptedFactory($this, $object);
                }

                $this->object = $object;
                $this->calls($this->object, $instantiator);
                $this->cacheStrategy?->set($this, $object);

                return $object;
            }
        }

        $this->object = $instantiator->newInstanceOf($this->class, $this->getArguments());
        $this->calls($this->object, $instantiator);
        $this->cacheStrategy?->set($this, $this->object);

        return $this->object;
    }

    /**
     * Factory.
     *
     * @param Instantiator $instantiator
     *
     * @return mixed
     * @throws ContainerException
     */
    protected function factory(Instantiator $instantiator): mixed
    {
        if (is_callable($this->factory)) {
            return ($this->factory)(...$this->getArguments());
        }

        return $instantiator->call($this->factory, $this->getArguments());
    }

    /**
     * Calls.
     *
     * @param object $object
     * @param Instantiator $instantiator
     *
     * @return object
     * @throws ContainerException
     */
    protected function calls(object $object, Instantiator $instantiator): object
    {
        foreach ($this->calls ?? [] as $call) {
            $instantiator->invokeMethod($this->object, $call[0], $call[1] ?? []);
        }

        return $object;
    }
}