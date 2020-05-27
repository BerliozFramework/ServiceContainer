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
 * Interface ServiceContainerAwareInterface.
 *
 * @package Berlioz\ServiceContainer
 */
interface ServiceContainerAwareInterface
{
    /**
     * Get service container.
     *
     * @return ServiceContainer|null
     */
    public function getServiceContainer(): ?ServiceContainer;

    /**
     * Set service container.
     *
     * @param ServiceContainer $serviceContainer
     *
     * @return static
     */
    public function setServiceContainer(ServiceContainer $serviceContainer);

    /**
     * Has service container?
     *
     * @return bool
     */
    public function hasServiceContainer(): bool;
}