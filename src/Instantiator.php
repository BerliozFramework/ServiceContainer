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

use Berlioz\ServiceContainer\Exception\InstantiatorException;
use Psr\Container\ContainerInterface;

/**
 * Class Instantiator.
 *
 * @package Berlioz\ServiceContainer
 */
class Instantiator
{
    /** @var \Berlioz\ServiceContainer\ClassIndex Class index */
    private $classIndex;
    /** @var \Psr\Container\ContainerInterface Container */
    private $container;

    /**
     * Instantiator constructor.
     *
     * @param \Berlioz\ServiceContainer\ClassIndex|null $classIndex
     * @param \Psr\Container\ContainerInterface         $container
     */
    public function __construct(?ClassIndex $classIndex = null, ?ContainerInterface $container = null)
    {
        $this->classIndex = $classIndex;
        $this->container = $container;
    }

    /////////////////
    /// CONTAINER ///
    /////////////////

    /**
     * Get container.
     *
     * @return null|\Psr\Container\ContainerInterface
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * Set container.
     *
     * @param \Psr\Container\ContainerInterface $container
     *
     * @return static
     */
    public function setContainer(ContainerInterface $container): Instantiator
    {
        $this->container = $container;

        return $this;
    }

    ///////////////////
    /// CLASS INDEX ///
    ///////////////////

    /**
     * Get class index.
     *
     * @return \Berlioz\ServiceContainer\ClassIndex
     */
    public function getClassIndex(): ClassIndex
    {
        if (is_null($this->classIndex)) {
            $this->classIndex = new ClassIndex();
        }

        return $this->classIndex;
    }

    /**
     * Set class index.
     *
     * @param \Berlioz\ServiceContainer\ClassIndex $classIndex
     *
     * @return static
     */
    public function setClassIndex(ClassIndex $classIndex): Instantiator
    {
        $this->classIndex = $classIndex;

        return $this;
    }

    ////////////////////////////
    /// INSTANTIATOR METHODS ///
    ////////////////////////////

    /**
     * Create new instance of a class.
     *
     * @param object|string $class               Class name or object
     * @param array         $arguments           Arguments
     * @param bool          $dependencyInjection Dependency injection? (default: true)
     *
     * @return mixed
     * @throws \Berlioz\ServiceContainer\Exception\InstantiatorException
     */
    public function newInstanceOf($class, array $arguments = [], bool $dependencyInjection = true)
    {
        try {
            // Reflection of class
            $reflectionClass = new \ReflectionClass($class);

            if (!is_null($constructor = $reflectionClass->getConstructor())) {
                // Dependency injection?
                if ($dependencyInjection) {
                    $arguments = $this->getDependencyInjectionParameters($constructor->getParameters(), $arguments);
                }
            }
        } catch (\Exception $e) {
            throw new InstantiatorException(sprintf('Error during dependency injection of class "%s"', $class), 0, $e);
        }

        if (is_null($constructor)) {
            return $reflectionClass->newInstanceWithoutConstructor();
        } else {
            return $reflectionClass->newInstanceArgs($arguments);
        }
    }

    /**
     * Invocation of method.
     *
     * @param object|string $class               Class or object
     * @param string        $method              Method name
     * @param array         $arguments           Arguments
     * @param bool          $dependencyInjection Dependency injection? (default: true)
     *
     * @return mixed
     * @throws \Berlioz\ServiceContainer\Exception\InstantiatorException
     */
    public function invokeMethod($class, string $method, array $arguments = [], bool $dependencyInjection = true)
    {
        // Check validity of first argument
        if (!(is_object($class) || (is_string($class) && class_exists($class)))) {
            throw new InstantiatorException(sprintf('First argument must be a valid class name or an object, %s given', gettype($class)));
        }

        try {
            // Reflection of method
            $reflectionMethod = new \ReflectionMethod($class, $method);

            // Create object from class
            if (!$reflectionMethod->isStatic() && is_string($class)) {
                throw new InstantiatorException('First argument must be an object if you want call a non static method');
            }

            // Dependency injection?
            if ($dependencyInjection) {
                $arguments = $this->getDependencyInjectionParameters($reflectionMethod->getParameters(), $arguments);
            }
        } catch (InstantiatorException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InstantiatorException(sprintf('Error during dependency injection of method "%s::%s"',
                                                    is_object($class) ? get_class($class) : $class,
                                                    $method), 0, $e);
        }

        // Static method
        if ($reflectionMethod->isStatic()) {
            return $reflectionMethod->invokeArgs(null, $arguments);
        }

        /** @var object $class */
        // Non static method
        return $reflectionMethod->invokeArgs($class, $arguments);
    }

    /**
     * Invocation of function.
     *
     * @param string $function            Function name
     * @param array  $arguments           Arguments
     * @param bool   $dependencyInjection Dependency injection? (default: true)
     *
     * @return mixed
     * @throws \Berlioz\ServiceContainer\Exception\InstantiatorException
     */
    public function invokeFunction(string $function, array $arguments = [], bool $dependencyInjection = true)
    {
        try {
            // Reflection of function
            $reflectionFunction = new \ReflectionFunction($function);

            // Dependency injection?
            if ($dependencyInjection) {
                $arguments = $this->getDependencyInjectionParameters($reflectionFunction->getParameters(), $arguments);
            }
        } catch (\Exception $e) {
            throw new InstantiatorException(sprintf('Error during dependency injection of function "%s"', $function), 0, $e);
        }

        return $reflectionFunction->invokeArgs($arguments);
    }

    /**
     * Get parameters ordered to inject.
     *
     * @param \ReflectionParameter[] $reflectionParameters
     * @param array                  $arguments
     *
     * @return array Parameters (ordered)
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function getDependencyInjectionParameters(array $reflectionParameters, array $arguments = []): array
    {
        $parameters = [];

        // Treat arguments
        $argumentsClass = [];
        foreach ($arguments as $name => &$argument) {
            if (!is_null($this->getContainer())) {
                // Service recursively
                if (is_string($argument) && substr($argument, 0, 1) == '@') {
                    $subServiceName = substr($argument, 1);
                    $argument = $this->getContainer()->get($subServiceName);
                }
            }

            // Get all classes of argument if it's an object.
            // It's necessary to know if argument can match with an injection.
            if (is_object($argument) && !($argument instanceof \stdClass)) {
                foreach ($this->getClassIndex()->getAllClasses($argument) as $class) {
                    $argumentsClass[$class][] = $name;
                }
            }
        }

        // Try to get all parameters values
        foreach ($reflectionParameters as $reflectionParameter) {
            if ($reflectionParameter instanceof \ReflectionParameter) {
                $parameters[$reflectionParameter->getName()] =
                    $this->getDependencyInjectionParameter($reflectionParameter,
                                                           $arguments,
                                                           $argumentsClass);
            }
        }

        return $parameters;
    }

    /**
     * Get parameter value for injection.
     *
     * @param \ReflectionParameter $reflectionParameter
     * @param array                $arguments
     * @param array                $argumentsClass
     *
     * @return mixed|null
     * @throws \Berlioz\ServiceContainer\Exception\InstantiatorException
     */
    private function getDependencyInjectionParameter(\ReflectionParameter $reflectionParameter, array &$arguments, array $argumentsClass)
    {
        // Parameter name in arguments list?
        if (array_key_exists($reflectionParameter->getName(), $arguments)) {
            $parameter = $arguments[$reflectionParameter->getName()];

            // Remove argument to do not use again
            unset($arguments[$reflectionParameter->getName()]);

            return $parameter;
        }

        // Parameter is class?
        if ($reflectionParameter->hasType() && !$reflectionParameter->getType()->isBuiltin()) {
            // Parameter type is in arguments class?
            if (array_key_exists($reflectionParameter->getType()->getName(), $argumentsClass)) {
                $argumentsFound = $argumentsClass[$reflectionParameter->getType()->getName()];

                // Argument is already available?
                if (($argumentFound = reset($argumentsFound)) !== false && isset($arguments[$argumentFound])) {
                    $parameter = $arguments[$argumentFound];

                    // Remove argument to do not use again
                    unset($arguments[$argumentFound]);

                    return $parameter;
                }
            }

            if (!is_null($this->getContainer())) {
                // Service exists with the same name and same type?
                if ($this->getContainer()->has($reflectionParameter->getName())
                    && is_a($service = $this->getContainer()->get($reflectionParameter->getName()), $reflectionParameter->getType()->getName())) {
                    return $service;
                }

                // Service exists with same class?
                if ($this->getContainer()->has($reflectionParameter->getType()->getName())
                    && is_a($service = $this->getContainer()->get($reflectionParameter->getType()->getName()), $reflectionParameter->getType()->getName())) {
                    return $service;
                }
            }
        }

        if ($reflectionParameter->isDefaultValueAvailable()) {
            try {
                return $reflectionParameter->getDefaultValue();
            } catch (\Exception $e) {
                throw new InstantiatorException(sprintf('Unable to get default value of parameter "%s"', $reflectionParameter->getName()));
            }
        } else {
            if ($reflectionParameter->allowsNull()) {
                return null;
            } else {
                throw new InstantiatorException(sprintf('Missing parameter "%s"', $reflectionParameter->getName()));
            }
        }
    }
}