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

namespace Berlioz\ServiceContainer\Tests\files;


class Service2 implements ServiceInterface
{
    public $param1;
    public $param2;
    public $param3;

    public function __construct(string $param1, Service1 $param2, bool $param3 = true)
    {
        $this->param1 = $param1;
        $this->param2 = $param2;
        $this->param3 = $param3;
    }
}