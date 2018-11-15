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
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Psr\Container\ContainerExceptionInterface;

class ServiceContainer implements ServiceContainerInterface, \Serializable
{
    /** @var \Berlioz\ServiceContainer\Instantiator Instantiator */
    private $instantiator;
    /** @var array Classes */
    private $classes = [];
    /** @var array Services constraints */
    private $constraints = [];
    /** @var array Services */
    private $services = [];
    /** @var array Initialization */
    private $initialization = [];

    /**
     * ServiceContainer constructor.
     *
     * @param array                                       $services     Services
     * @param array                                       $constraints  Constraints
     * @param \Berlioz\ServiceContainer\Instantiator|null $instantiator Instantiator
     */
    public function __construct(array $services = [], array $constraints = [], ?Instantiator $instantiator = null)
    {
        if (!is_null($instantiator)) {
            $this->setInstantiator($instantiator);
        }

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
    public function serialize(): string
    {
        $services = $this->services;
        array_walk($services,
            function (&$value) {
                $value['object'] = null;
            });

        return serialize(['classIndex'  => $this->getInstantiator()->getClassIndex(),
                          'classes'     => $this->classes,
                          'constraints' => $this->constraints,
                          'services'    => $services]);
    }

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        $tmpUnserialized = unserialize($serialized);

        if (!empty($tmpUnserialized['classIndex'])) {
            $this->getInstantiator()->setClassIndex($tmpUnserialized['classIndex']);
        }
        $this->classes = $tmpUnserialized['classes'];
        $this->constraints = $tmpUnserialized['constraints'];
        $this->services = $tmpUnserialized['services'];
    }

    ////////////////////
    /// INSTANTIATOR ///
    ////////////////////

    /**
     * Get instantiator.
     *
     * @return \Berlioz\ServiceContainer\Instantiator
     */
    public function getInstantiator(): Instantiator
    {
        if (is_null($this->instantiator)) {
            $this->instantiator = new Instantiator(null, $this);
            $this->register('instantiator', $this->instantiator);
        }

        return $this->instantiator;
    }

    /**
     * Set instantiator.
     *
     * @param \Berlioz\ServiceContainer\Instantiator $instantiator
     *
     * @return static
     */
    public function setInstantiator(Instantiator $instantiator): ServiceContainer
    {
        $this->instantiator = $instantiator;
        $this->register('instantiator', $this->instantiator);

        return $this;
    }

    ////////////////////////////////
    /// REGISTRATION OF SERVICES ///
    ////////////////////////////////

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
            foreach ($this->getInstantiator()->getClassIndex()->getAllClasses($class) as $aClass) {
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

    ///////////////////////////////
    /// CONSTRAINTS OF SERVICES ///
    ///////////////////////////////

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
                        $service = $this->getInstantiator()->newInstanceOf($this->services[$id]['class'], $this->services[$id]['arguments']);
                        $this->services[$id]['object'] = $service;

                        // Calls
                        foreach ($this->services[$id]['calls'] as $call) {
                            $this->getInstantiator()->invokeMethod($service, $call['method'], $call['arguments'] ?? []);
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
               || isset($this->classes[$id]);
    }
}