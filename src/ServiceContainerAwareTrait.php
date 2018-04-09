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


use Psr\Container\ContainerInterface;

/**
 * Describes a service container-aware instance.
 */
trait ServiceContainerAwareTrait
{
    /** @var \Psr\Container\ContainerInterface Service container */
    private $serviceContainer;

    /**
     * Get service container.
     *
     * @return \Psr\Container\ContainerInterface|null
     */
    public function getServiceContainer(): ?ContainerInterface
    {
        return $this->serviceContainer;
    }

    /**
     * Set service container.
     *
     * @param \Psr\Container\ContainerInterface $serviceContainer
     *
     * @return static
     */
    public function setServiceContainer(ContainerInterface $serviceContainer)
    {
        $this->serviceContainer = $serviceContainer;

        return $this;
    }

    /**
     * Has service container?
     *
     * @return bool
     */
    public function hasServiceContainer(): bool
    {
        return !is_null($this->serviceContainer);
    }
}