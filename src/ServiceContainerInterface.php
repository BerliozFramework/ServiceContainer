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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Interface ServiceContainerInterface.
 *
 * @package Berlioz\ServiceContainer
 */
interface ServiceContainerInterface extends ContainerInterface
{
    /**
     * Add a service.
     *
     * @param Service $service
     *
     * @return static
     * @throws ContainerExceptionInterface
     */
    public function add(Service $service): ServiceContainerInterface;
}