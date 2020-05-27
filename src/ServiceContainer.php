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
use Psr\Container\NotFoundExceptionInterface;
use Serializable;

/**
 * Class ServiceContainer.
 *
 * @package Berlioz\ServiceContainer
 */
class ServiceContainer implements ServiceContainerInterface, Serializable
{
    /** @var Instantiator Instantiator */
    private $instantiator;
    /** @var array Classes */
    private $classes = [];
    /** @var Service[] Services */
    private $services = [];

    /**
     * ServiceContainer constructor.
     *
     * @param Instantiator|null $instantiator Instantiator
     *
     * @throws ContainerException
     */
    public function __construct(?Instantiator $instantiator = null)
    {
        if (null !== $instantiator) {
            $this->setInstantiator($instantiator);
        }

        // Register me into services
        $this->add(new Service($this));
    }

    /**
     * @inheritdoc
     * @throws ContainerException
     */
    public function serialize(): string
    {
        return serialize(
            [
                'classIndex' => $this->getInstantiator()->getClassIndex(),
                'classes' => $this->classes,
                'services' => $this->services
            ]
        );
    }

    /**
     * @inheritdoc
     * @throws ContainerException
     */
    public function unserialize($serialized)
    {
        $tmpUnserialized = unserialize($serialized);

        $this->classes = $tmpUnserialized['classes'];
        $this->services = $tmpUnserialized['services'];
        if (!empty($tmpUnserialized['classIndex'])) {
            $this->getInstantiator()->setClassIndex($tmpUnserialized['classIndex']);
        }

        // Register me into services
        $this->add(new Service($this));
    }

    ////////////////////
    /// INSTANTIATOR ///
    ////////////////////

    /**
     * Get instantiator.
     *
     * @return Instantiator
     * @throws ContainerException
     */
    public function getInstantiator(): Instantiator
    {
        if (null === $this->instantiator) {
            $this->setInstantiator(new Instantiator(null, $this));
        }

        return $this->instantiator;
    }

    /**
     * Set instantiator.
     *
     * @param Instantiator $instantiator
     *
     * @throws ContainerException
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
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
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

        // Add new service
        $this->add(new Service($id));

        return $this->get($id);
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