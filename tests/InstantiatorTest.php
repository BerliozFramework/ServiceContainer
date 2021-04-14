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

namespace Berlioz\ServiceContainer\Tests;

use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Exception\ArgumentException;
use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Instantiator;
use Berlioz\ServiceContainer\Service\Service;
use Berlioz\ServiceContainer\Tests\Asset\Service1;
use Berlioz\ServiceContainer\Tests\Asset\Service2;
use Berlioz\ServiceContainer\Tests\Asset\Service3;
use Berlioz\ServiceContainer\Tests\Asset\Service4;
use Berlioz\ServiceContainer\Tests\Asset\Service9;
use PHPUnit\Framework\TestCase;

class InstantiatorTest extends TestCase
{
    public function testCall()
    {
        $instantiator = new Instantiator();

        $this->assertEquals(
            'foobar',
            $instantiator->call(fn($var) => 'foo' . $var, ['var' => 'bar'], true)
        );
    }

    public function testNewInstanceOf()
    {
        $instantiator = new Instantiator();
        $service = $instantiator->newInstanceOf(
            Service3::class,
            [
                'param1' => 'test',
                'param2' => 'test',
                'param3' => 3,
                'param4' => 'test',
            ]
        );
        $this->assertInstanceOf(Service3::class, $service);
    }

    public function testNewInstanceOf_withTooMuchParams()
    {
        $instantiator = new Instantiator();
        $service = $instantiator->newInstanceOf(
            Service1::class,
            [
                'param1' => 'test',
                'param2' => 'test',
                'param3' => 3,
                'param4' => 'test',
                'param5' => 'test',
            ]
        );
        $this->assertInstanceOf(Service1::class, $service);
    }

    public function testNewInstanceOf_withNotNamedParameters()
    {
        $service1 = new Service1('param1', 'param2', 1);
        $instantiator = new Instantiator();
        $service = $instantiator->newInstanceOf(
            Service2::class,
            [
                'param2' => $service1,
                'param1' => 'test',
            ]
        );
        $this->assertInstanceOf(Service2::class, $service);
        $this->assertEquals($service1, $service->getParam2());
    }

    public function testNewInstanceOf_optionalParameters()
    {
        $instantiator = new Instantiator();
        $service = $instantiator->newInstanceOf(
            Service3::class,
            [
                'param1' => 'test',
                'param2' => 'test',
            ]
        );
        $this->assertInstanceOf(Service3::class, $service);
        $this->assertNull($service->param3);
        $this->assertEquals('test', $service->param4);
    }

    public function testNewInstanceOf_missingParameter()
    {
        $this->expectException(ContainerException::class);
        $instantiator = new Instantiator(null);
        $instantiator->newInstanceOf(
            Service3::class,
            [
                'param1' => 'test',
                'param4' => 'test2',
            ]
        );
    }

    public function testNewInstanceOf_withoutConstructor()
    {
        $instantiator = new Instantiator(null);
        $service = $instantiator->newInstanceOf(
            Service4::class,
            [
                'param1' => 'test',
                'param4' => 'test2',
            ]
        );
        $this->assertInstanceOf(Service4::class, $service);

        $service = $instantiator->newInstanceOf(Service4::class);
        $this->assertInstanceOf(Service4::class, $service);
    }

    public function testNewInstanceOf_withUnionTypes()
    {
        $instantiator = new Instantiator();
        $result = $instantiator->newInstanceOf(
            Service9::class,
            [
                'param1' => $service4 = new Service4(),
                'param2' => 2,
            ]
        );
        $this->assertSame($service4, $result->param1);
        $this->assertSame(2, $result->param2);
    }


    public function testNewInstanceOf_withUnionTypesAutoWiring()
    {
        $instantiator = new Instantiator();
        $result = $instantiator->newInstanceOf(
            Service9::class,
            [
                'param2' => 2,
            ]
        );
        $this->assertInstanceOf(Service4::class, $result->param1);
        $this->assertSame(2, $result->param2);
    }

    public function testNewInstanceOf_withAlias()
    {
        $container = new Container();
        $container->addService(
            new Service($service1 = new Service1('param1', 'param2', 1), 'mySuperAlias'),
        );
        $instantiator = new Instantiator($container);

        $result = $instantiator->newInstanceOf(
            Service2::class,
            [
                'param1' => 'param1Value',
                'param2' => '@mySuperAlias'
            ]
        );
        $this->assertSame($service1, $result->getParam2());
    }

    public function testNewInstanceOf_withUnknownAlias()
    {
        $this->expectException(ArgumentException::class);
        $this->expectExceptionMessage(ArgumentException::missingService('mySuperUnknownAlias')->getMessage());

        $container = new Container();
        $container->addService(
            new Service($service1 = new Service1('param1', 'param2', 1), 'mySuperAlias'),
        );
        $instantiator = new Instantiator($container);

        $instantiator->newInstanceOf(
            Service2::class,
            [
                'param1' => 'param1Value',
                'param2' => '@mySuperUnknownAlias'
            ]
        );
    }

    public function testInvokeMethod()
    {
        $container = new Container();
        $container->addService(
            new Service($service1 = new Service1('param1', 'param2', 1)),
            new Service(new Service2('param1', $service1))
        );
        $instantiator = new Instantiator($container);

        $service = $container->get(Service2::class);
        $result = $instantiator->invokeMethod(
            $service,
            'test',
            ['param' => ($str = 'toto')]
        );
        $this->assertEquals(sprintf('It\'s a test "%s"', $str), $result);
    }

    public function testInvokeStaticMethod()
    {
        $container = new Container();
        $container->addService(
            new Service($service1 = new Service1('param1', 'param2', 1)),
            new Service(new Service2('param1', $service1))
        );
        $instantiator = new Instantiator($container);

        $result = $instantiator->invokeMethod(Service2::class, 'testStatic');
        $this->assertEquals(sprintf('It\'s a test "%s"', Service1::class), $result);
    }

    public function testInvokeNonStaticMethod()
    {
        $instantiator = new Instantiator();

        $result = $instantiator->invokeMethod(Service4::class, 'test');
        $this->assertEquals(sprintf('It\'s a test "%s"', Service4::class), $result);
    }
}
