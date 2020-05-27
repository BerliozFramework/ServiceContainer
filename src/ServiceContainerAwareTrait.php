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

/**
 * Describes a service container-aware instance.
 */
trait ServiceContainerAwareTrait
{
    /** @var ServiceContainer Service container */
    private $serviceContainer;

    /**
     * Get service container.
     *
     * @return ServiceContainer|null
     */
    public function getServiceContainer(): ?ServiceContainer
    {
        return $this->serviceContainer;
    }

    /**
     * Set service container.
     *
     * @param ServiceContainer $serviceContainer
     *
     * @return static
     */
    public function setServiceContainer(ServiceContainer $serviceContainer)
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
        return null !== $this->serviceContainer;
    }
}