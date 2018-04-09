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

            $this->associateClassToService($finalConfiguration['class'], $alias, false);

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
    private function associateClassToService(string $className, string $alias, bool $autoload = false)
    {
        $this->classes[$className][] = $alias;

        foreach (class_parents($className, true) as $classParent) {
            $this->classes[$classParent][] = $alias;
        }

        foreach (class_implements($className, true) as $classParent) {
            $this->classes[$classParent][] = $alias;
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

        $this->services[$serviceAlias] = $this->dependencyInjection($serviceClass, $serviceArguments);

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
     * Object creation with dependency injection from services.
     *
     * @param string $className Class name
     * @param array  $arguments Named arguments
     *
     * @return object
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function dependencyInjection(string $className, array $arguments = [])
    {
        $parameters = [];

        // Class exists ?
        if (!class_exists($className)) {
            throw new NotFoundException(sprintf('Service "%s" doesn\'t exists', $className));
        } else {
            // If class has constructor ?
            if (method_exists($className, '__construct')) {
                // Construct parameters list for constructor
                try {
                    $reflectionMethod = new \ReflectionMethod($className, '__construct');
                    $reflectionParameters = $reflectionMethod->getParameters();

                    if (count($reflectionParameters) > 0) {
                        foreach ($reflectionParameters as $reflectionParameter) {
                            // Parameter given in configuration and waiting type is built in ?
                            if (isset($arguments[$reflectionParameter->getName()])
                                && !$this->isValidServiceConfiguration($arguments[$reflectionParameter->getName()])
                                && (!$reflectionParameter->hasType() || $reflectionParameter->getType()->isBuiltin())) {
                                $parameters[$reflectionParameter->getName()] = $arguments[$reflectionParameter->getName()];
                            } else {
                                try {
                                    // Parameter is class ?
                                    if (($reflectionParameter->hasType() && !$reflectionParameter->getType()->isBuiltin())
                                        || (isset($arguments[$reflectionParameter->getName()]) && $this->isValidServiceConfiguration($arguments[$reflectionParameter->getName()]))) {
                                        // Argument is object ? So service ?
                                        if (isset($arguments[$reflectionParameter->getName()])
                                            && $this->isValidServiceConfiguration($arguments[$reflectionParameter->getName()])) {
                                            if (($subServiceConfiguration = $this->makeConfiguration($arguments[$reflectionParameter->getName()])) !== false) {
                                                $parameters[$reflectionParameter->getName()] = $this->createService($subServiceConfiguration);
                                            } else {
                                                throw new ContainerException(sprintf('Sub service "%s" in argument of "%s" class has bad configuration', $reflectionParameter->getName(), $className));
                                            }
                                        } else {
                                            // Get waiting service in configuration if available or declared class
                                            $waitingService = $arguments[$reflectionParameter->getName()] ?? $reflectionParameter->getType()->getName();

                                            $parameters[$reflectionParameter->getName()] = $this->get($waitingService);
                                        }
                                    } else {
                                        throw new ContainerException(sprintf('Missing "%s" parameter in configuration for "%s" class', $reflectionParameter->getName(), $className));
                                    }
                                } catch (\Exception $e) {
                                    if ($reflectionParameter->isDefaultValueAvailable()) {
                                        $parameters[$reflectionParameter->getName()] = $reflectionParameter->getDefaultValue();
                                    } else {
                                        if ($reflectionParameter->allowsNull()) {
                                            $parameters[$reflectionParameter->getName()] = null;
                                        } else {
                                            throw $e;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (ContainerException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw new ContainerException(sprintf('Error during reflection of class "%s"', $className), 0, $e);
                }
            }
        }

        // Construction of service
        try {
            if (count($parameters) > 0) {
                return new $className(...array_values($parameters));
            } else {
                return new $className;
            }
        } catch (\Exception $e) {
            throw new ContainerException(sprintf('Error during creation of class "%s"', $className), 0, $e);
        }
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
        if (isset($this->initialization[$id]) && $this->initialization[$id] === true) {
            throw new ContainerException(sprintf('Recursive call of service "%s"', $id));
        } else {
            $this->initialization[$id] = true;

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

            $this->initialization[$id] = false;
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
}