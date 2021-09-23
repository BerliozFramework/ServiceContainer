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

namespace Berlioz\ServiceContainer\Tests\Provider;

use Berlioz\ServiceContainer\Tests\Container\FakeServiceProvider;

class FakeServiceProviderTest extends ProviderTestCase
{
    public function providers(): array
    {
        return [
            [new FakeServiceProvider()],
        ];
    }
}