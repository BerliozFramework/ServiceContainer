<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\ServiceContainer\Exception;

class ArgumentException extends InstantiatorException
{
    /**
     * Missing argument.
     *
     * @param string $name
     *
     * @return static
     */
    public static function missingArgument(string $name): static
    {
        return new static(sprintf('Missing argument "%s"', $name));
    }

    /**
     * Missing service.
     *
     * @param string $name
     *
     * @return static
     */
    public static function missingService(string $name): static
    {
        return new static(sprintf('Service "%s" does not found in container ', $name));
    }
}