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

namespace Berlioz\ServiceContainer\Tests;

use Berlioz\ServiceContainer\Exception\ClassIndexException;
use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Exception\InstantiatorException;
use Berlioz\ServiceContainer\Instantiator;
use Berlioz\ServiceContainer\Tests\files\Service1;
use Berlioz\ServiceContainer\Tests\files\Service2;
use Berlioz\ServiceContainer\Tests\files\Service3;
use Berlioz\ServiceContainer\Tests\files\Service4;
use Berlioz\ServiceContainer\Tests\files\Service9;
use PHPUnit\Framework\TestCase;

class InstantiatorTest extends TestCase
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testNewInstanceOf()
    {
        $instantiator = new Instantiator;
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

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testNewInstanceOfWithNotNamedParameters()
    {
        $service1 = new Service1('param1', 'param2', 1);
        $instantiator = new Instantiator;
        $service = $instantiator->newInstanceOf(
            Service2::class,
            [
                'param1' => 'test',
                'aService' => $service1,
            ]
        );
        $this->assertInstanceOf(Service2::class, $service);
        $this->assertEquals($service1, $service->getParam2());
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
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

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testNewInstanceOf_missingParameter()
    {
        $this->expectException(ContainerException::class);
        $instantiator = new Instantiator;
        $instantiator->newInstanceOf(
            Service3::class,
            [
                'param1' => 'test',
                'param4' => 'test2',
            ]
        );
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testNewInstanceOf_withoutConstructor()
    {
        $instantiator = new Instantiator;
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

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @requires PHP 7.8
     */
    public function testNewInstanceOf_withUnionTypes()
    {
        $serviceContainer = ServiceContainerTest::getServiceContainer();
        $instantiator = new Instantiator(null, $serviceContainer);
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

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testInvokeMethod()
    {
        $serviceContainer = ServiceContainerTest::getServiceContainer();
        $instantiator = new Instantiator(null, $serviceContainer);
        $service = $serviceContainer->get(Service2::class);
        $result = $instantiator->invokeMethod(
            $service,
            'test',
            ['param' => ($str = 'toto')]
        );
        $this->assertEquals(sprintf('It\'s a test "%s"', $str), $result);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testInvokeStaticMethod()
    {
        $serviceContainer = ServiceContainerTest::getServiceContainer();
        $instantiator = new Instantiator(null, $serviceContainer);

        $result = $instantiator->invokeMethod(Service2::class, 'testStatic');
        $this->assertEquals(sprintf('It\'s a test "%s"', Service1::class), $result);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testInvokeStaticMethodWithNonStaticMethod()
    {
        $this->expectException(InstantiatorException::class);
        $serviceContainer = ServiceContainerTest::getServiceContainer();
        $instantiator = new Instantiator(null, $serviceContainer);
        $instantiator->invokeMethod(Service4::class, 'test');
    }

    public function testClassIndexWithNonexistentClass()
    {
        $this->expectException(ClassIndexException::class);
        $instantiator = new Instantiator();
        $instantiator->getClassIndex()->getAllClasses('FooBarClass');
    }

    public function testClassIndexWithInterface()
    {
        $instantiator = new Instantiator();
        $classes = $instantiator->getClassIndex()->getAllClasses('Iterator');
        $this->assertEquals(
            [
                'Iterator',
                'Traversable',
            ],
            $classes
        );
    }
}
