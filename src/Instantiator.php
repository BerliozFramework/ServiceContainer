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

use Berlioz\ServiceContainer\Exception\ClassIndexException;
use Berlioz\ServiceContainer\Exception\InstantiatorException;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionUnionType;
use stdClass;

/**
 * Class Instantiator.
 *
 * @package Berlioz\ServiceContainer
 */
class Instantiator
{
    /** @var ClassIndex Class index */
    private $classIndex;
    /** @var ContainerInterface Container */
    private $container;

    /**
     * Instantiator constructor.
     *
     * @param ClassIndex|null $classIndex
     * @param ContainerInterface $container
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
     * @return null|ContainerInterface
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * Set container.
     *
     * @param ContainerInterface $container
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
     * @return ClassIndex
     */
    public function getClassIndex(): ClassIndex
    {
        if (null === $this->classIndex) {
            $this->classIndex = new ClassIndex();
        }

        return $this->classIndex;
    }

    /**
     * Set class index.
     *
     * @param ClassIndex $classIndex
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
     * @param object|string $class Class name or object
     * @param array $arguments Arguments
     * @param bool $dependencyInjection Dependency injection? (default: true)
     *
     * @return mixed
     * @throws InstantiatorException
     */
    public function newInstanceOf($class, array $arguments = [], bool $dependencyInjection = true)
    {
        try {
            // Reflection of class
            $reflectionClass = new ReflectionClass($class);

            if (null !== ($constructor = $reflectionClass->getConstructor())) {
                // Dependency injection?
                if ($dependencyInjection) {
                    $arguments = $this->getDependencyInjectionParameters($constructor, $arguments);
                }
            }
        } catch (InstantiatorException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new InstantiatorException(sprintf('Error during dependency injection of class "%s"', $class), 0, $e);
        }

        if (null === $constructor) {
            return $reflectionClass->newInstanceWithoutConstructor();
        }

        return $reflectionClass->newInstanceArgs($arguments);
    }

    /**
     * Invocation of method.
     *
     * @param object|string $class Class or object
     * @param string $method Method name
     * @param array $arguments Arguments
     * @param bool $dependencyInjection Dependency injection? (default: true)
     *
     * @return mixed
     * @throws InstantiatorException
     */
    public function invokeMethod($class, string $method, array $arguments = [], bool $dependencyInjection = true)
    {
        // Check validity of first argument
        if (!(is_object($class) || (is_string($class) && class_exists($class)))) {
            throw new InstantiatorException(
                sprintf('First argument must be a valid class name or an object, %s given', gettype($class))
            );
        }

        try {
            // Reflection of method
            $reflectionMethod = new ReflectionMethod($class, $method);

            // Create object from class
            if (!$reflectionMethod->isStatic() && is_string($class)) {
                throw new InstantiatorException(
                    'First argument must be an object if you want call a non static method'
                );
            }

            // Dependency injection?
            if ($dependencyInjection) {
                $arguments = $this->getDependencyInjectionParameters($reflectionMethod, $arguments);
            }
        } catch (InstantiatorException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new InstantiatorException(
                sprintf(
                    'Error during dependency injection of method "%s::%s"',
                    is_object($class) ? get_class($class) : $class,
                    $method
                ), 0, $e
            );
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
     * @param string $function Function name
     * @param array $arguments Arguments
     * @param bool $dependencyInjection Dependency injection? (default: true)
     *
     * @return mixed
     * @throws InstantiatorException
     */
    public function invokeFunction(string $function, array $arguments = [], bool $dependencyInjection = true)
    {
        try {
            // Reflection of function
            $reflectionFunction = new ReflectionFunction($function);

            // Dependency injection?
            if ($dependencyInjection) {
                $arguments = $this->getDependencyInjectionParameters($reflectionFunction, $arguments);
            }
        } catch (InstantiatorException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new InstantiatorException(
                sprintf('Error during dependency injection of function "%s"', $function),
                0,
                $e
            );
        }

        return $reflectionFunction->invokeArgs($arguments);
    }

    /**
     * Get parameters ordered to inject.
     *
     * @param ReflectionFunctionAbstract $reflectionFunction
     * @param array $arguments
     *
     * @return array Parameters (ordered)
     * @throws ClassIndexException
     * @throws InstantiatorException
     */
    private function getDependencyInjectionParameters(
        ReflectionFunctionAbstract $reflectionFunction,
        array $arguments = []
    ): array {
        $parameters = [];

        // Treat arguments
        $argumentsClass = [];
        foreach ($arguments as $name => &$argument) {
            if (null !== $this->getContainer()) {
                // Service recursively
                if (is_string($argument) && substr($argument, 0, 1) == '@') {
                    $subServiceName = substr($argument, 1);
                    $argument = $this->getContainer()->get($subServiceName);
                }
            }

            // Get all classes of argument if it's an object.
            // It's necessary to know if argument can match with an injection.
            if (is_object($argument) && !($argument instanceof stdClass)) {
                foreach ($this->getClassIndex()->getAllClasses($argument) as $class) {
                    $argumentsClass[$class][] = $name;
                }
            }
        }

        // Try to get all parameters values
        foreach ($reflectionFunction->getParameters() as $reflectionParameter) {
            if (!$reflectionParameter instanceof ReflectionParameter) {
                continue;
            }

            $parameters[$reflectionParameter->getName()] =
                $this->getDependencyInjectionParameter(
                    $reflectionFunction,
                    $reflectionParameter,
                    $arguments,
                    $argumentsClass
                );
        }

        return $parameters;
    }

    /**
     * Get parameter value for injection.
     *
     * @param ReflectionFunctionAbstract $reflectionFunction
     * @param ReflectionParameter $reflectionParameter
     * @param array $arguments
     * @param array $argumentsClass
     *
     * @return mixed|null
     * @throws InstantiatorException
     */
    private function getDependencyInjectionParameter(
        ReflectionFunctionAbstract $reflectionFunction,
        ReflectionParameter $reflectionParameter,
        array &$arguments,
        array $argumentsClass
    ) {
        // Parameter name in arguments list?
        if (array_key_exists($reflectionParameter->getName(), $arguments)) {
            $parameter = $arguments[$reflectionParameter->getName()];

            // Remove argument to do not use again
            unset($arguments[$reflectionParameter->getName()]);

            return $parameter;
        }

        $types = $reflectionParameter->getType();
        if (!class_exists('\ReflectionUnionType') ||
            !($types = $reflectionParameter->getType()) instanceof ReflectionUnionType) {
            $types = [$types];
        }

        foreach ($types as $type) {
            // Parameter is class?
            if ($reflectionParameter->hasType() && !$type->isBuiltin()) {
                // Parameter type is in arguments class?
                if (array_key_exists($type->getName(), $argumentsClass)) {
                    $argumentsFound = $argumentsClass[$type->getName()];

                    // Argument is already available?
                    if (($argumentFound = reset($argumentsFound)) !== false && isset($arguments[$argumentFound])) {
                        $parameter = $arguments[$argumentFound];

                        // Remove argument to do not use again
                        unset($arguments[$argumentFound]);

                        return $parameter;
                    }
                }

                if (null !== $this->getContainer()) {
                    // Service exists with the same name and same type?
                    if ($this->getContainer()->has($reflectionParameter->getName())
                        && is_a(
                            $service = $this->getContainer()->get($reflectionParameter->getName()),
                            $type->getName()
                        )) {
                        return $service;
                    }

                    // Service exists with same class?
                    try {
                        $service = $this->getContainer()->get($type->getName());

                        if (is_a($service, $type->getName())) {
                            return $service;
                        }
                    } catch (ContainerExceptionInterface $e) {
                    }
                }
            }
        }

        if ($reflectionParameter->isDefaultValueAvailable()) {
            try {
                return $reflectionParameter->getDefaultValue();
            } catch (Exception $e) {
                $message = sprintf(
                    'Unable to get default value of parameter "%s" of "%s"',
                    $reflectionParameter->getName(),
                    $reflectionFunction->getName()
                );
                if ($reflectionFunction instanceof ReflectionMethod) {
                    $message .= sprintf(' in class "%s"', $reflectionFunction->getDeclaringClass()->getName());
                }

                throw new InstantiatorException($message);
            }
        }

        if (!$reflectionParameter->allowsNull()) {
            $message = sprintf(
                'Missing parameter "%s" of "%s"',
                $reflectionParameter->getName(),
                $reflectionFunction->getName()
            );
            if ($reflectionFunction instanceof ReflectionMethod) {
                $message .= sprintf(' in class "%s"', $reflectionFunction->getDeclaringClass()->getName());
            }

            throw new InstantiatorException($message);
        }

        return null;
    }
}