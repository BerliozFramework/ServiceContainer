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
use Psr\Container\ContainerInterface;

class ServiceContainer implements ContainerInterface
{
    /** @var array Services constraints */
    private $constraints = [];
    /** @var array Services configuration */
    private $servicesConfiguration;
    /** @var array Classes */
    private $classes;
    /** @var array Services */
    private $services;
    /** @var array Initialization */
    private $initialization;

    /**
     * ServiceContainer constructor.
     *
     * @param array|null $servicesConfiguration
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(?array $servicesConfiguration = null)
    {
        $this->servicesConfiguration = [];
        $this->classes = [];
        $this->services = [];
        $this->initialization = [];

        // Register me into services
        $this->registerObjectAsService($this, 'ServiceContainer');

        if (!empty($servicesConfiguration)) {
            $this->registerServices($servicesConfiguration);
        }
    }

    /**
     * Register object as service.
     *
     * @param object      $object
     * @param string|null $alias
     *
     * @return string Name of service
     */
    public function registerObjectAsService($object, string $alias = null)
    {
        $className = get_class($object);

        $this->services[$alias ?? $className] = $object;

        $this->associateClassToService($className, $alias ?? $className);

        return $alias ?? $className;
    }

    /**
     * Register services.
     *
     * @param array $servicesConfiguration
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function registerServices(array $servicesConfiguration)
    {
        foreach ($servicesConfiguration as $alias => $serviceConfiguration) {
            $this->registerService($serviceConfiguration, $alias);
        }
    }

    /**
     * Register service.
     *
     * @param string|array $serviceConfiguration
     * @param string|null  $alias
     *
     * @return string Name of service
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function registerService($serviceConfiguration, string $alias = null): string
    {
        if (($finalConfiguration = $this->makeConfiguration($serviceConfiguration)) !== false) {
            $alias = $finalConfiguration['alias'] ?? $alias;
            $finalConfiguration['alias'] = $alias;

            $this->associateClassToService($finalConfiguration['class'], $alias, true);

            $this->servicesConfiguration[$alias] = $finalConfiguration;

            return $alias;
        } else {
            throw new ContainerException(sprintf('Bad configuration format for service named "%s", must be class name or array', $alias ?? (string) $serviceConfiguration['class'] ?? 'Unknown'));
        }
    }

    /**
     * Associate class name to a service.
     *
     * @param string $className Class name
     * @param string $alias     Alias of service
     * @param bool   $autoload  Auto load
     */
    private function associateClassToService(string $className, string $alias, bool $autoload = true)
    {
        $this->classes[$className][] = $alias;
        $this->classes[$className] = array_unique($this->classes[$className]);

        foreach (class_parents($className, $autoload) as $classParent) {
            $this->classes[$classParent][] = $alias;
            $this->classes[$classParent] = array_unique($this->classes[$classParent]);
        }

        foreach (class_implements($className, $autoload) as $classParent) {
            $this->classes[$classParent][] = $alias;
            $this->classes[$classParent] = array_unique($this->classes[$classParent]);
        }
    }

    /**
     * Make configuration.
     *
     * @param array $serviceConfiguration
     *
     * @return array|false
     */
    private function makeConfiguration($serviceConfiguration)
    {
        $finalConfiguration = ['alias' => null, 'class' => null, 'arguments' => []];

        if (is_string($serviceConfiguration)) {
            $finalConfiguration['class'] = $serviceConfiguration;
        } else {
            if ($this->isValidServiceConfiguration($serviceConfiguration)) {
                $finalConfiguration['alias'] = $serviceConfiguration['alias'] ?? null;
                $finalConfiguration['class'] = ltrim($serviceConfiguration['class'], '\\');
                $finalConfiguration['arguments'] = (array) ($serviceConfiguration['arguments'] ?? []);
            } else {
                return false;
            }
        }

        return $finalConfiguration;
    }

    private function isValidServiceConfiguration($serviceConfiguration): bool
    {
        if (is_array($serviceConfiguration)) {
            if (!empty($serviceConfiguration['class'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create service from configuration.
     *
     * @param array $serviceConfiguration
     *
     * @return object
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function createService(array $serviceConfiguration)
    {
        // Get service class & name
        $serviceClass = $serviceConfiguration['class'];
        $serviceAlias = $serviceConfiguration['alias'] ?? $serviceClass;
        $serviceArguments = $serviceConfiguration['arguments'];

        // Check constraints
        $this->checkConstraints($serviceAlias, $serviceClass);

        // Construct service
        $this->services[$serviceAlias] = $this->newInstanceOf($serviceClass, $serviceArguments);

        $this->associateClassToService($serviceClass, $serviceAlias);

        return $this->services[$serviceAlias];
    }

    /**
     * Check constraints for a service name.
     *
     * @param string $name  Name of service
     * @param string $class Class name of service
     *
     * @return void
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    private function checkConstraints(string $name, string $class): void
    {
        // Check constraint
        if (isset($this->constraints[$name])) {
            if (!is_a($class, $this->constraints[$name], true)) {
                throw new ContainerException(sprintf('Service "%s" must implements "%s" class', $name, $this->constraints[$name]));
            }
        }
    }

    /**
     * Get constraints.
     *
     * @return array
     */
    public function getConstraints(): array
    {
        return $this->constraints ?? [];
    }

    /**
     * Set constraints.
     *
     * @param array $constraints
     *
     * @return \Berlioz\ServiceContainer\ServiceContainer
     */
    public function setConstraints(array $constraints): ServiceContainer
    {
        $this->constraints = $constraints;

        return $this;
    }

    /**
     * Set constraint for service.
     *
     * @param string $serviceName Service name
     * @param string $class       Class name
     *
     * @return \Berlioz\ServiceContainer\ServiceContainer
     */
    public function addConstraint(string $serviceName, string $class): ServiceContainer
    {
        $this->constraints[$serviceName] = $class;

        return $this;
    }

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
            // Add service to currently initialization
            $this->initialization[] = $id;

            // Check if not already instanced?
            if (isset($this->services[$id])) {
                $service = $this->services[$id];
            } else {
                // Check if a config is available for service id
                if (!empty($this->servicesConfiguration[$id])) {
                    $service = $this->createService($this->servicesConfiguration[$id]);
                } else {
                    // Check if service has alias
                    if (!empty($this->classes[$id])) {
                        $service = $this->get(reset($this->classes[$id]));
                    } else {
                        $id = $this->registerService($id);
                        $service = $this->get($id);
                    }
                }
            }

            // Delete service from currently initialization
            if (($key = array_search($id, $this->initialization)) !== false) {
                unset($this->initialization[$key]);
            }
        }

        return $service;
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
        return isset($this->servicesConfiguration[$id])
               || isset($this->services[$id])
               || isset($this->classes[$id])
               || class_exists($id);
    }

    /**
     * Create new instance of a class.
     *
     * @param object|string $class               Class name or object
     * @param array         $arguments           Arguments
     * @param bool          $dependencyInjection Dependency injection? (default: true)
     *
     * @return mixed
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    public function newInstanceOf($class, array $arguments = [], bool $dependencyInjection = true)
    {
        // Reflection of class
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\Exception $e) {
            if (!class_exists($class)) {
                throw new NotFoundException(sprintf('Class "%s" doesn\'t exists', $class), 0, $e);
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
     * Invocation of method.
     *
     * @param object $object              Object
     * @param string $method              Method name
     * @param array  $arguments           Arguments
     * @param bool   $dependencyInjection Dependency injection? (default: true)
     *
     * @return mixed
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
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
                throw new NotFoundException(sprintf('Method "%s::%s" doesn\'t exists', get_class($object), $method), 0, $e);
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
     * Invocation of function.
     *
     * @param string $function            Function name
     * @param array  $arguments           Arguments
     * @param bool   $dependencyInjection Dependency injection? (default: true)
     *
     * @return mixed
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
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
    public function getDependencyInjectionParameters(array $reflectionParameters, array $arguments = []): array
    {
        $parameters = [];

        foreach ($reflectionParameters as $reflectionParameter) {
            if ($reflectionParameter instanceof \ReflectionParameter) {
                // Parameter given in configuration and waiting type is built in?
                if (array_key_exists($reflectionParameter->getName(), $arguments)
                    && !$this->isValidServiceConfiguration($arguments[$reflectionParameter->getName()])
                    && (!$reflectionParameter->hasType() || $reflectionParameter->getType()->isBuiltin())) {
                    $parameters[$reflectionParameter->getName()] = $arguments[$reflectionParameter->getName()];
                } else {
                    try {
                        // Parameter is class?
                        if (($reflectionParameter->hasType() && !$reflectionParameter->getType()->isBuiltin())
                            || (array_key_exists($reflectionParameter->getName(), $arguments) && $this->isValidServiceConfiguration($arguments[$reflectionParameter->getName()]))) {
                            // Argument is object? So service?
                            if (array_key_exists($reflectionParameter->getName(), $arguments)
                                && $this->isValidServiceConfiguration($arguments[$reflectionParameter->getName()])) {
                                if (($subServiceConfiguration = $this->makeConfiguration($arguments[$reflectionParameter->getName()])) !== false) {
                                    $parameters[$reflectionParameter->getName()] = $this->createService($subServiceConfiguration);
                                } else {
                                    throw new ContainerException(sprintf('Service "%s" has bad configuration', $reflectionParameter->getName()));
                                }
                            } else {
                                // Get waiting service in configuration if available or declared class
                                $waitingService = $arguments[$reflectionParameter->getName()] ?? $reflectionParameter->getType()->getName();

                                $parameters[$reflectionParameter->getName()] = $this->get($waitingService);
                            }
                        } else {
                            throw new ContainerException(sprintf('Missing "%s" parameter', $reflectionParameter->getName()));
                        }
                    } catch (\Exception $e) {
                        if ($reflectionParameter->isDefaultValueAvailable()) {
                            $parameters[$reflectionParameter->getName()] = $reflectionParameter->getDefaultValue();
                        } else {
                            if ($reflectionParameter->allowsNull()) {
                                $parameters[$reflectionParameter->getName()] = null;
                            } else {
                                if ($e instanceof ContainerExceptionInterface) {
                                    throw $e;
                                } else {
                                    throw new ContainerException(sprintf('Error during creation of dependencies with parameter "%s"', $reflectionParameter->getName()), 0, $e);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $parameters;
    }
}