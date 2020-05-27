<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\ServiceContainer;

use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Exception\InstantiatorException;
use Serializable;

/**
 * Class Service.
 *
 * @package Berlioz\ServiceContainer
 */
class Service implements Serializable
{
    /** @var string Class name */
    private $class;
    /** @var string Factory class::method */
    private $factory;
    /** @var string Alias */
    private $alias;
    /** @var mixed[] Arguments */
    private $arguments;
    /** @var mixed[] Calls */
    private $calls;
    /** @var object Object */
    private $object;
    /** @var bool Initialized? */
    private $initialized = false;
    /** @var bool Initialization in progress? */
    private $initialization = false;

    /**
     * Service constructor.
     *
     * @param string|object $class
     * @param null|string $alias
     *
     * @throws ContainerException
     */
    public function __construct($class, ?string $alias = null)
    {
        // Check validity of first argument
        if (!(is_object($class) || is_string($class))) {
            throw new ContainerException(
                sprintf('First argument must be a valid class name or an object, %s given', gettype($class))
            );
        }

        // Alias
        $this->alias = $alias;

        // Get class name of object
        if (is_string($class)) {
            $this->class = ltrim($class, '\\');
            $this->object = null;
        } else {
            $this->object = $class;
            $this->class = get_class($class);
            $this->initialized = true;
        }
    }

    /**
     * @inheritdoc
     */
    public function serialize(): string
    {
        return serialize(
            [
                'class' => $this->class,
                'factory' => $this->factory,
                'alias' => $this->alias,
                'arguments' => $this->arguments,
                'calls' => $this->calls
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        $tmpUnserialized = unserialize($serialized);

        $this->class = $tmpUnserialized['class'];
        $this->factory = $tmpUnserialized['factory'];
        $this->alias = $tmpUnserialized['alias'];
        $this->arguments = $tmpUnserialized['arguments'];
        $this->calls = $tmpUnserialized['calls'];
        $this->initialized = false;
        $this->initialization = false;
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
     * @return null|string
     */
    public function getAlias(): ?string
    {
        return $this->alias ?: $this->class;
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
     * @return Service
     */
    public function addArgument(string $name, $value): Service
    {
        $this->arguments[$name] = $value;

        return $this;
    }

    /**
     * Add arguments.
     *
     * @param mixed[] $arguments
     *
     * @return Service
     */
    public function addArguments(array $arguments): Service
    {
        $this->arguments = array_replace($this->arguments ?? [], $arguments);

        return $this;
    }

    /**
     * Add call.
     *
     * @param string $method
     * @param mixed[] $arguments
     *
     * @return Service
     */
    public function addCall(string $method, array $arguments = []): Service
    {
        $this->calls[] = [$method, $arguments];

        return $this;
    }

    /**
     * Set factory
     *
     * @param string $factory
     *
     * @return Service
     * @throws ContainerException
     */
    public function setFactory(string $factory): Service
    {
        $factoryExploded = explode('::', $factory, 2);

        if (!class_exists($factoryExploded[0]) || !method_exists($factoryExploded[0], $factoryExploded[1])) {
            throw new ContainerException(
                sprintf('Must be a valid factory class and static method, "%s" given', $factory)
            );
        }

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
     * @throws InstantiatorException
     */
    public function get(Instantiator $instantiator)
    {
        // Already initialized?
        if (null !== $this->object) {
            return $this->object;
        }

        // Already in initialization?
        if ($this->initialization) {
            throw new ContainerException(sprintf('Recursive initialization of service "%s"', $this->class));
        }

        // Create instance of object
        if (null === $this->factory) {
            $this->object = $instantiator->newInstanceOf($this->class, $this->getArguments());
        } else {
            $factory = explode('::', $this->factory, 2);
            $object = $instantiator->invokeMethod(
                $factory[0],
                $factory[1],
                array_merge(['service' => $this], $this->getArguments())
            );

            if (!$object instanceof $this->class) {
                throw new ContainerException(
                    sprintf(
                        'Factory "%s" must returns a "%s" class, "%s" returned',
                        $this->factory,
                        $this->class,
                        get_class($object)
                    )
                );
            }

            $this->object = $object;
        }

        // Calls
        foreach ($this->calls ?? [] as $call) {
            $instantiator->invokeMethod($this->object, $call[0], $call[1] ?? []);
        }

        return $this->object;
    }
}