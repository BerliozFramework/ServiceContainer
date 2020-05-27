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

use Berlioz\ServiceContainer\Exception\ClassIndexException;

/**
 * Class ClassIndex.
 *
 * @package Berlioz\ServiceContainer
 */
class ClassIndex
{
    /** @var array Classes */
    private $classes = [];

    /**
     * Get all classes of a class (herself, parents classes and interfaces).
     *
     * @param string|object $class Class name
     * @param bool $autoload Auto load
     *
     * @return array
     * @throws ClassIndexException
     */
    public function getAllClasses($class, bool $autoload = true): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (!isset($this->classes[$class])) {
            if (!class_exists($class, $autoload) && !interface_exists($class, $autoload)) {
                throw new ClassIndexException(sprintf('Class "%s" does not exists', $class));
            }

            $parentsClasses = class_parents($class, $autoload);
            $implementedInterfaces = class_implements($class, $autoload);

            if ($parentsClasses === false || $implementedInterfaces === false) {
                throw new ClassIndexException(sprintf('Unable to get all classes of class "%s"', $class));
            }

            $classes = array_merge([ltrim($class, '\\')], $parentsClasses, $implementedInterfaces);
            $this->classes[$class] = array_unique(array_values($classes));
        }

        return $this->classes[$class];
    }
}