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

namespace Berlioz\ServiceContainer;

use Berlioz\ServiceContainer\Exception\ArgumentException;
use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Exception\InstantiatorException;
use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/**
 * Class Instantiator.
 */
class Instantiator
{
    /**
     * Instantiator constructor.
     *
     * @param ContainerInterface|null $container
     */
    public function __construct(protected ?ContainerInterface $container = null)
    {
    }

    /**
     * Call.
     *
     * @param string|Closure $subject
     * @param array $arguments
     * @param bool $autoWiring
     *
     * @return mixed
     * @throws ContainerException
     */
    public function call(string|Closure $subject, array $arguments = [], bool $autoWiring = true): mixed
    {
        // Function?
        if (is_callable($subject)) {
            return $this->invokeFunction($subject, $arguments, $autoWiring);
        }

        // Method
        if (true === str_contains($subject, '::')) {
            $subject = explode('::', $subject, 2);

            return $this->invokeMethod($subject[0], $subject[1], $arguments, $autoWiring);
        }

        return $this->newInstanceOf($subject, $arguments, $autoWiring);
    }

    ////////////////////////////
    /// INSTANTIATOR METHODS ///
    ////////////////////////////

    /**
     * Create new instance of a class.
     *
     * @param object|string $class Class name or object
     * @param array $arguments Arguments
     * @param bool $autoWiring
     *
     * @return object
     * @throws ContainerException
     */
    public function newInstanceOf(object|string $class, array $arguments = [], bool $autoWiring = true): object
    {
        try {
            // Reflection of class
            $reflectionClass = new ReflectionClass($class);

            if (null !== ($constructor = $reflectionClass->getConstructor())) {
                // Dependency injection?
                if ($autoWiring) {
                    $arguments = $this->getArguments($constructor, $arguments);
                }

                return $reflectionClass->newInstanceArgs($arguments);
            }

            return $reflectionClass->newInstanceWithoutConstructor();
        } catch (Throwable $exception) {
            throw InstantiatorException::classError($class, $exception);
        }
    }

    /**
     * Invocation of method.
     *
     * @param object|string $class Class or object
     * @param string $method Method name
     * @param array $arguments Arguments
     * @param bool $autoWiring
     *
     * @return mixed
     * @throws ContainerException
     */
    public function invokeMethod(
        object|string $class,
        string $method,
        array $arguments = [],
        bool $autoWiring = true
    ): mixed {
        try {
            // Reflection of method
            $reflectionMethod = new ReflectionMethod($class, $method);

            // Dependency injection?
            if ($autoWiring) {
                $arguments = $this->getArguments($reflectionMethod, $arguments);
            }

            // Static method
            if ($reflectionMethod->isStatic()) {
                return $reflectionMethod->invokeArgs(null, $arguments);
            }

            // Get instance into container
            if (is_string($class)) {
                if (true === $this->container?->has($class)) {
                    $class = $this->container->get($class);
                }
            }

            // Create new instance of class if not object
            if (is_string($class)) {
                $class = $this->newInstanceOf($class);
            }

            return $reflectionMethod->invokeArgs($class, $arguments);
        } catch (Throwable $exception) {
            throw InstantiatorException::methodError($class, $method, $exception);
        }
    }

    /**
     * Invocation of function.
     *
     * @param string|Closure $function Function name
     * @param array $arguments Arguments
     * @param bool $autoWiring
     *
     * @return mixed
     * @throws ContainerException
     */
    public function invokeFunction(string|Closure $function, array $arguments = [], bool $autoWiring = true): mixed
    {
        try {
            // Reflection of function
            $reflectionFunction = new ReflectionFunction($function);

            // Dependency injection?
            if ($autoWiring) {
                $arguments = $this->getArguments($reflectionFunction, $arguments);
            }

            return $reflectionFunction->invokeArgs($arguments);
        } catch (Throwable $exception) {
            throw InstantiatorException::functionError($function, $exception);
        }
    }

    /**
     * Get arguments to inject.
     *
     * @param ReflectionFunctionAbstract $reflectionFunction
     * @param array $arguments
     *
     * @return array
     * @throws ArgumentException
     * @throws ContainerException
     */
    protected function getArguments(
        ReflectionFunctionAbstract $reflectionFunction,
        array $arguments = []
    ): array {
        foreach ($reflectionFunction->getParameters() as $reflectionParameter) {
            // Argument already given
            if (array_key_exists($reflectionParameter->getName(), $arguments)) {
                $argument = &$arguments[$reflectionParameter->getName()];

                if (is_string($argument)) {
                    // It's an alias?
                    if (str_starts_with($argument, '@')) {
                        if (true === $this->container?->has(substr($argument, 1))) {
                            $argument = $this->container->get(substr($argument, 1));
                        }
                    }
                }
                continue;
            }

            // Search with types
            if (true === $reflectionParameter->hasType()) {
                foreach ($this->getParameterTypes($reflectionParameter) as $reflectionType) {
                    if (true === $reflectionType->isBuiltin()) {
                        continue;
                    }

                    if (true === $this->container?->has($reflectionType->getName())) {
                        $arguments[$reflectionParameter->getName()] = $this->container->get($reflectionType->getName());
                        continue 2;
                    }

                    $arguments[$reflectionParameter->getName()] = $this->newInstanceOf($reflectionType->getName());
                }
            }

            // Skip if default value available
            if ($reflectionParameter->isDefaultValueAvailable()) {
                continue;
            }

            // Allows null value?
            if (true === $reflectionParameter->allowsNull()) {
                $arguments[$reflectionParameter->getName()] = null;
                continue;
            }

            throw ArgumentException::missingArgument($reflectionParameter->getName());
        }

        return $arguments;
    }

    /**
     * Get parameter types.
     *
     * @param ReflectionParameter $parameter
     *
     * @return ReflectionNamedType[]
     */
    protected function getParameterTypes(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionType) {
            return [$type];
        }

        if ($type instanceof ReflectionUnionType) {
            return $type->getTypes();
        }

        return [];
    }
}