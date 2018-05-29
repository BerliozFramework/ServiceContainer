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
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
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
     * Configuration must be a class name or an array describes the service:
     *     [ 'alias'     => 'NameOfService',
     *       'class'     => '\My\Class',
     *       'arguments' => [ 'argument' => 'value' ] ]
     *
     * @param string|array $service
     * @param string|null  $alias
     *
     * @return string Name of service
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function registerService($service, string $alias = null): string
    {
        if (($serviceConfiguration = $this->makeConfiguration($service)) !== false) {
            $alias = $serviceConfiguration['alias'] ?? $alias ?? $serviceConfiguration['class'];
            $serviceConfiguration['alias'] = $alias;

            $this->associateClassToService($serviceConfiguration['class'], $alias, true);

            $this->servicesConfiguration[$alias] = $serviceConfiguration;

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
     *
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    private function associateClassToService(string $className, string $alias, bool $autoload = true)
    {
        foreach ($this->getAllClasses($className, $autoload) as $aClass) {
            $this->classes[$aClass][] = $alias;
            $this->classes[$aClass] = array_unique($this->classes[$aClass]);
        }
    }

    /**
     * Get all classes of a class (herself, parents classes and interfaces).
     *
     * @param string|object $class    Class name
     * @param bool          $autoload Auto load
     *
     * @return array
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    private function getAllClasses($class, bool $autoload = true): array
    {
        // Check validity of first argument
        if (!(is_object($class) || is_string($class))) {
            throw new ContainerException(sprintf('First argument must be a class name or an object, %s given', gettype($class)));
        }

        if (is_object($class)) {
            $class = get_class($class);
        }

        $classes = array_merge([ltrim($class, '\\')],
                               $resultClassParents = @class_parents($class, $autoload),
                               $resultClassImplements = @class_implements($class, $autoload));

        if ($resultClassParents === false || $resultClassImplements === false) {
            throw new ContainerException(sprintf('Unable to load service "%s", class does not exists', $class));
        }

        return array_unique($classes);
    }

    /**
     * Make configuration.
     *
     * @param string|array $service
     *
     * @return array|false
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    private function makeConfiguration($service)
    {
        $configuration = ['alias' => null, 'class' => null, 'arguments' => []];

        if (is_string($service)) {
            if (class_exists($service)) {
                $configuration['class'] = $service;
            } else {
                throw new ContainerException(sprintf('Class "%s" does not exist', $service));
            }
        } else {
            if (is_array($service) && !empty($service['class'])) {
                $configuration['alias'] = $service['alias'] ?? null;
                $configuration['class'] = ltrim($service['class'], '\\');
                $configuration['arguments'] = (array) ($service['arguments'] ?? []);
            } else {
                return false;
            }
        }

        return $configuration;
    }

    /**
     * Create service from configuration.
     *
     * @param array $configuration
     *
     * @return object
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function createService(array $configuration)
    {
        // Get service class & name
        $serviceClass = $configuration['class'];
        $serviceAlias = $configuration['alias'] ?? $serviceClass;
        $serviceArguments = $configuration['arguments'];

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
            try {
                // Add service to currently initialization
                $this->initialization[] = $originalId = $id;

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
            } finally {
                // Delete service from currently initialization
                if (($key = array_search($originalId, $this->initialization)) !== false) {
                    unset($this->initialization[$key]);
                }
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

        // Treat arguments
        $argumentsClass = [];
        foreach ($arguments as $name => &$argument) {
            // Service recursively
            if (is_string($argument) && substr($argument, 0, 1) == '@') {
                $subServiceName = substr($argument, 1);

                if ($this->has($subServiceName)) {
                    $argument = $this->get($subServiceName);
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
                        if ($this->has($reflectionParameter->getType()->getName())) {
                            $parameterValue = $this->get($reflectionParameter->getType()->getName());
                            $parameterValueFound = true;
                        } else {
                            // Present in arguments?
                            if (isset($argumentsClass[$reflectionParameter->getType()->getName()])) {
                                $parameterValue = $arguments[reset($argumentsClass[$reflectionParameter->getType()->getName()])];
                                $parameterValueFound = true;
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