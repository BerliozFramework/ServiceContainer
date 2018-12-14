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

use Berlioz\ServiceContainer\Exception\NotFoundException;

class ServiceContainer implements ServiceContainerInterface, \Serializable
{
    /** @var \Berlioz\ServiceContainer\Instantiator Instantiator */
    private $instantiator;
    /** @var array Classes */
    private $classes = [];
    /** @var \Berlioz\ServiceContainer\Service[] Services */
    private $services = [];

    /**
     * ServiceContainer constructor.
     *
     * @param \Berlioz\ServiceContainer\Instantiator|null $instantiator Instantiator
     *
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    public function __construct(?Instantiator $instantiator = null)
    {
        if (!is_null($instantiator)) {
            $this->setInstantiator($instantiator);
        }

        // Register me into services
        $this->add(new Service($this));
    }

    /**
     * @inheritdoc
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    public function serialize(): string
    {
        return serialize(['classIndex' => $this->getInstantiator()->getClassIndex(),
                          'classes'    => $this->classes,
                          'services'   => $this->services]);
    }

    /**
     * @inheritdoc
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    public function unserialize($serialized)
    {
        $tmpUnserialized = unserialize($serialized);

        if (!empty($tmpUnserialized['classIndex'])) {
            $this->getInstantiator()->setClassIndex($tmpUnserialized['classIndex']);
        }
        $this->classes = $tmpUnserialized['classes'];
        $this->services = $tmpUnserialized['services'];
        
        // Register the service container and the initiator again to register their objects
        $this->register('ServiceContainer', $this);
        $this->register('instantiator', $this->getInstantiator());
    }

    ////////////////////
    /// INSTANTIATOR ///
    ////////////////////

    /**
     * Get instantiator.
     *
     * @return \Berlioz\ServiceContainer\Instantiator
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    public function getInstantiator(): Instantiator
    {
        if (is_null($this->instantiator)) {
            $this->setInstantiator(new Instantiator(null, $this));
        }

        return $this->instantiator;
    }

    /**
     * Set instantiator.
     *
     * @param \Berlioz\ServiceContainer\Instantiator $instantiator
     *
     * @throws \Berlioz\ServiceContainer\Exception\ContainerException
     */
    private function setInstantiator(Instantiator $instantiator)
    {
        $this->instantiator = $instantiator;
        $this->add(new Service($this->instantiator));
    }

    ////////////////////////////////
    /// REGISTRATION OF SERVICES ///
    ////////////////////////////////

    /**
     * @inheritdoc
     */
    public function add(Service $service): ServiceContainerInterface
    {
        // Associate all classes to the service
        foreach ($this->getInstantiator()->getClassIndex()->getAllClasses($service->getClass()) as $aClass) {
            $this->classes[$aClass][] = $service->getAlias();
        }

        // Associate alias to service object (null if not initialized) and configuration
        $this->services[$service->getAlias()] = $service;

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
        // Check if service registered?
        if (isset($this->services[$id])) {
            return $this->services[$id]->get($this->getInstantiator());
        }

        // ID is a class and registered for a service?
        if (!empty($this->classes[$id])) {
            // Get first alias
            return $this->get(reset($this->classes[$id]));
        }

        // Register new service, thrown Exception if not found
        if (!class_exists($id)) {
            throw new NotFoundException(sprintf('Unable to find "%s" service', $id));
        }

        return $this->add(new Service($id));
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
        return isset($this->services[$id]) || isset($this->classes[$id]);
    }
}