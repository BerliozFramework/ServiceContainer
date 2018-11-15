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

use Psr\Container\ContainerInterface;

interface ServiceContainerInterface extends ContainerInterface
{
    /**
     * Register a service.
     *
     * Format for $calls argument:
     *   [ [ 'method' => 'myMethod',
     *       'arguments' => [ 'argument1' => true ] ] ]
     *
     * @param string        $alias     Alias
     * @param string|object $class     Class name or object
     * @param array         $arguments Arguments for creation of object (if class given in second parameter)
     * @param array         $calls     Callable methods after creation of object (if class given in second parameter)
     *
     * @return \Berlioz\ServiceContainer\ServiceContainerInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function register(string $alias, $class, array $arguments = [], array $calls = []): ServiceContainerInterface;

    /**
     * Register multiple services.
     *
     * Format:
     *   [ 'alias' => [ 'class' => '\MyClass\Of\Service',
     *                  'arguments' => [ 'argument1' => true ],
     *                  'calls' => [ [ 'method' => 'myMethod',
     *                                 'arguments' => [ 'argument1' => true ] ] ] ] ]
     *
     * @param array $services Services
     *
     * @return \Berlioz\ServiceContainer\ServiceContainerInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function registerServices(array $services): ServiceContainerInterface;

    /**
     * Get constraints.
     *
     * @return array
     */
    public function getConstraints(): array;

    /**
     * Set constraints.
     *
     * @param array $constraints
     *
     * @return \Berlioz\ServiceContainer\ServiceContainer
     */
    public function setConstraints(array $constraints): ServiceContainer;

    /**
     * Set constraint for service.
     *
     * @param string $alias Service alias
     * @param string $class Class name
     *
     * @return \Berlioz\ServiceContainer\ServiceContainer
     */
    public function addConstraint(string $alias, string $class): ServiceContainer;
}