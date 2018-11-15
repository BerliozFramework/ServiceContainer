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

class ClassIndex
{
    /** @var array Classes */
    private $classes = [];

    /**
     * Get all classes of a class (herself, parents classes and interfaces).
     *
     * @param string|object $class    Class name
     * @param bool          $autoload Auto load
     *
     * @return array
     * @throws \Berlioz\ServiceContainer\Exception\ClassIndexException
     */
    public function getAllClasses($class, bool $autoload = true): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (!isset($this->classes[$class])) {
            $classes = array_merge([ltrim($class, '\\')],
                                   $resultClassParents = @class_parents($class, $autoload),
                                   $resultClassImplements = @class_implements($class, $autoload));

            if ($resultClassParents === false || $resultClassImplements === false) {
                throw new ClassIndexException(sprintf('Unable to get all classes of class "%s"', $class));
            }

            $this->classes[$class] = array_unique($classes);
        }

        return $this->classes[$class];
    }
}