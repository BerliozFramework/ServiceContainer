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
 * Interface ContainerAwareInterface.
 *
 * @package Berlioz\ServiceContainer
 */
interface ContainerAwareInterface
{
    /**
     * Get container.
     *
     * @return Container|null
     */
    public function getContainer(): ?Container;

    /**
     * Set container.
     *
     * @param Container $container
     *
     * @return static
     */
    public function setContainer(Container $container): static;

    /**
     * Has container?
     *
     * @return bool
     */
    public function hasContainer(): bool;
}