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

namespace Berlioz\ServiceContainer;

use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Psr\Container\ContainerExceptionInterface;

class ServiceContainer implements ServiceContainerInterface
{
    /** @var array Services constraints */
    private $constraints = [];
    /** @var array Classes */
    private $classes;
    /** @var array Services */
    private $services;
    /** @var array Initialization */
    private $initialization;

    /**
     * ServiceContainer constructor.
     *
     * @param array $services    Services
     * @param array $constraints Constraints
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(array $services = [], array $constraints = [])
    {
        $this->classes = [];
        $this->services = [];
        $this->initialization = [];

        // Set constraints
        $this->setConstraints($constraints);

        // Register me into services
        $this->register('ServiceContainer', $this);

        // Register all services
        $this->registerServices($services);
    }

    /**
     * @inheritdoc
     */
    public function register(string $alias, $class, array $arguments = [], array $calls = []): ServiceContainerInterface
    {
        try {
            // Check validity of first argument
            if (!(is_object($class) || is_string($class))) {
                throw new ContainerException(sprintf('First argument must be a class name or an object, %s given', gettype($class)));
            }

            // Get class name of object
            $object = null;
            if (is_string($class)) {
                $class = ltrim($class, '\\');

                if (!class_exists($class)) {
                    throw new NotFoundException(sprintf('Class "%s" does not exists', $class));
                }
            } else {
                $object = $class;
                $class = get_class($class);
            }

            // Check constraints
            $this->checkConstraints($alias, $class);

            // Associate all classes to the alias
            foreach ($this->getAllClasses($class) as $aClass) {
                $this->classes[$aClass][] = $alias;
            }

            // Associate alias to service object (null if not initialized) and configuration
            $this->services[$alias] = ['object'    => $object,
                                       'class'     => $class,
                                       'arguments' => $arguments,
                                       'calls'     => $calls];

            return $this;
        } catch (ContainerExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ContainerException(sprintf('Unable to register service "%s"', $alias), 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function registerServices(array $services): ServiceContainerInterface
    {
        foreach ($services as $alias => $service) {
            if (empty($service['class'])) {
                throw new ContainerException(sprintf('Missing class in configuration of service "%s"', $alias));
            }

            $this->register($alias,
                            $service['class'],
                            $service['arguments'] ?? [],
                            $service['calls'] ?? []);
        }

        return $this;
    }

    /**
     * Get all classes of a class (herself, parents classes and interfaces).
     *
     * @param string|object $object   Class name
     * @param bool          $autoload Auto load
     *
     * @return array
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    private function getAllClasses($object, bool $autoload = true): array
    {
        if (is_object($object)) {
            $class = get_class($object);
        } else {
            $class = $object;
        }

        $classes = array_merge([ltrim($class, '\\')],
                               $resultClassParents = @class_parents($class, $autoload),
                               $resultClassImplements = @class_implements($class, $autoload));

        if ($resultClassParents === false || $resultClassImplements === false) {
            throw new ContainerException(sprintf('Unable to get all classes of class "%s"', $class));
        }

        return array_unique($classes);
    }

    ///////////////////
    /// CONSTRAINTS ///
    ///////////////////

    /**
     * Check constraints for a service alias.
     *
     * @param string $alias Alias of service
     * @param string $class Class name of service
     *
     * @return void
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    private function checkConstraints(string $alias, string $class): void
    {
        // Check constraint
        if (isset($this->constraints[$alias])) {
            if (!is_a($class, $this->constraints[$alias], true)) {
                throw new ContainerException(sprintf('Service "%s" must implements "%s" class', $alias, $this->constraints[$alias]));
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getConstraints(): array
    {
        return $this->constraints ?? [];
    }

    /**
     * @inheritdoc
     */
    public function setConstraints(array $constraints): ServiceContainer
    {
        $this->constraints = $constraints;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addConstraint(string $alias, string $class): ServiceContainer
    {
        $this->constraints[$alias] = $class;

        return $this;
    }

    ///////////////////////////
    /// CONTAINER INTERFACE ///
    ///////////////////////////

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return mixed Entry.
     * @throws \Psr\Container\NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws \Psr\Container\ContainerExceptionInterface Error while retrieving the entry.
     */
    public function get($id)
    {
        if (in_array($id, $this->initialization)) {
            throw new ContainerException(sprintf('Recursive call of service "%s"', $id));
        } else {
            try {
                // Add service to currently initialization
                $this->initialization[] = $originalId = $id;

                // Check if service registered?
                if (isset($this->services[$id])) {
                    // Get service already initialized
                    if (!is_null($this->services[$id]['object'])) {
                        $service = $this->services[$id]['object'];
                    } else {
                        // Create service
                        $service = $this->newInstanceOf($this->services[$id]['class'], $this->services[$id]['arguments']);
                        $this->services[$id]['object'] = $service;

                        // Calls
                        foreach ($this->services[$id]['calls'] as $call) {
                            $this->invokeMethod($service, $call['method'], $call['arguments'] ?? []);
                        }
                    }
                } else {
                    // Check if service has alias
                    if (!empty($this->classes[$id])) {
                        // Get first alias
                        $service = $this->get(reset($this->classes[$id]));
                    } else {
                        // Register new service, thrown Exception if not found
                        $this->register($id, $id);
                        $this->finishInitialization($originalId);

                        $service = $this->get($id);
                    }
                }
            } finally {
                $this->finishInitialization($originalId);
            }
        }

        return $service;
    }

    /**
     * Finish initialization of service.
     *
     * @param string $id
     */
    private function finishInitialization(string $id)
    {
        // Delete service from currently initialization
        if (($key = array_search($id, $this->initialization)) !== false) {
            unset($this->initialization[$key]);
        }
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id): bool
    {
        return isset($this->services[$id])
               || isset($this->classes[$id])
               || class_exists($id);
    }

    ////////////////////////////
    /// DEPENDENCY INJECTION ///
    ////////////////////////////

    /**
     * @inheritdoc
     */
    public function newInstanceOf($class, array $arguments = [], bool $dependencyInjection = true)
    {
        // Reflection of class
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\Exception $e) {
            if (!class_exists($class)) {
                throw new NotFoundException(sprintf('Class "%s" does not exists', $class), 0, $e);
            }

            throw new ContainerException(sprintf('Error during reflection: %s', $e->getMessage()), 0, $e);
        }

        // Dependency injection?
        if ($dependencyInjection) {
            if (!is_null($constructor = $reflectionClass->getConstructor())) {
                try {
                    $arguments = $this->getDependencyInjectionParameters($constructor->getParameters(), $arguments);
                } catch (ContainerExceptionInterface $e) {
                    throw new ContainerException(sprintf('Error during dependency injection of class "%s"', $class), 0, $e);
                }
            }
        }

        return $reflectionClass->newInstanceArgs($arguments);
    }

    /**
     * @inheritdoc
     */
    public function invokeMethod($object, string $method, array $arguments = [], bool $dependencyInjection = true)
    {
        // Check validity of first argument
        if (!is_object($object)) {
            throw new ContainerException(sprintf('First argument must be a valid object, %s given', gettype($object)));
        }

        // Reflection of method
        try {
            $reflectionMethod = new \ReflectionMethod($object, $method);
        } catch (\Exception $e) {
            if (!method_exists($object, $method)) {
                throw new NotFoundException(sprintf('Method "%s::%s" does not exists', get_class($object), $method), 0, $e);
            }

            throw new ContainerException(sprintf('Error during reflection: %s', $e->getMessage()), 0, $e);
        }

        // Dependency injection?
        if ($dependencyInjection) {
            try {
                $arguments = $this->getDependencyInjectionParameters($reflectionMethod->getParameters(), $arguments);
            } catch (ContainerExceptionInterface $e) {
                throw new ContainerException(sprintf('Error during dependency injection of method "%s::%s"', get_class($object), $method), 0, $e);
            }
        }

        return $reflectionMethod->invokeArgs($object, $arguments);
    }

    /**
     * @inheritdoc
     */
    public function invokeFunction(string $function, array $arguments = [], bool $dependencyInjection = true)
    {
        // Reflection of function
        try {
            $reflectionFunction = new \ReflectionFunction($function);
        } catch (\Exception $e) {
            throw new ContainerException(sprintf('Error during reflection of function "%s"', $function), 0, $e);
        }

        // Dependency injection?
        if ($dependencyInjection) {
            try {
                $arguments = $this->getDependencyInjectionParameters($reflectionFunction->getParameters(), $arguments);
            } catch (ContainerExceptionInterface $e) {
                throw new ContainerException(sprintf('Error during dependency injection of function "%s"', $function), 0, $e);
            }
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
            // Service recursively
            if (is_string($argument) && substr($argument, 0, 1) == '@') {
                $subServiceName = substr($argument, 1);

                if ($this->has($subServiceName)) {
                    $argument = $this->get($subServiceName);
                } else {
                    throw new NotFoundException(sprintf('Service "%s" not found', $subServiceName));
                }
            }

            if (is_object($argument) && !($argument instanceof \stdClass)) {
                foreach ($this->getAllClasses($argument) as $class) {
                    $argumentsClass[$class][] = $name;
                }
            }
        }

        // Try to get all parameters values
        foreach ($reflectionParameters as $reflectionParameter) {
            if ($reflectionParameter instanceof \ReflectionParameter) {
                $parameterValue = null;
                $parameterValueFound = false;

                // Parameter in arguments?
                if (array_key_exists($reflectionParameter->getName(), $arguments)) {
                    $parameterValue = $arguments[$reflectionParameter->getName()];
                    $parameterValueFound = true;
                } else {
                    // Parameter is class?
                    if ($reflectionParameter->hasType() && !$reflectionParameter->getType()->isBuiltin()) {
                        // Service exists?
                        if (!empty($this->classes[$reflectionParameter->getType()->getName()])) {
                            $classes = $this->classes[$reflectionParameter->getType()->getName()];

                            if (in_array($reflectionParameter->getName(), $classes)) {
                                $parameterValue = $this->get($reflectionParameter->getName());
                            } else {
                                $parameterValue = $this->get(reset($classes));
                            }
                            $parameterValueFound = true;
                        } else {
                            // Present in arguments?
                            if (isset($argumentsClass[$reflectionParameter->getType()->getName()])) {
                                $parameterValue = $arguments[reset($argumentsClass[$reflectionParameter->getType()->getName()])];
                                $parameterValueFound = true;
                            } else {
                                // Try to create argument object
                                if ($this->has($reflectionParameter->getType()->getName())) {
                                    $parameterValue = $this->get($reflectionParameter->getType()->getName());
                                    $parameterValueFound = true;
                                }
                            }
                        }
                    }
                }

                // Default value?
                if ($parameterValueFound === false) {
                    if ($reflectionParameter->isDefaultValueAvailable()) {
                        $parameterValue = $reflectionParameter->getDefaultValue();
                    } else {
                        if ($reflectionParameter->allowsNull()) {
                            $parameterValue = null;
                        } else {
                            throw new ContainerException(sprintf('Missing parameter "%s"', $reflectionParameter->getName()));
                        }
                    }
                }

                $parameters[$reflectionParameter->getName()] = $parameterValue;
            }
        }

        return $parameters;
    }
}