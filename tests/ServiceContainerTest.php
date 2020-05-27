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

use Berlioz\ServiceContainer\Exception\InstantiatorException;
use Berlioz\ServiceContainer\Service;
use Berlioz\ServiceContainer\ServiceContainer;
use Berlioz\ServiceContainer\Tests\files\Service1;
use Berlioz\ServiceContainer\Tests\files\Service2;
use Berlioz\ServiceContainer\Tests\files\Service5;
use Berlioz\ServiceContainer\Tests\files\Service6;
use Berlioz\ServiceContainer\Tests\files\Service7;
use Berlioz\ServiceContainer\Tests\files\Service8;
use Berlioz\ServiceContainer\Tests\files\ServiceFactory;
use PHPUnit\Framework\TestCase;

class ServiceContainerTest extends TestCase
{
    public static function getConfig()
    {
        return
            [
                "aliasService1"  =>
                    ["class"     => Service1::class,
                     "arguments" => ["param1" => "test",
                                     "param2" => "test",
                                     "param3" => 1],
                     "calls"     => [
                         [
                             "method"    => "increaseParam3",
                             "arguments" => [
                                 "nb" => 5,
                             ],
                         ],
                         [
                             "method"    => "increaseParam3",
                             "arguments" => [
                                 "nb" => 5,
                             ],
                         ],
                     ]],
                "aliasService1X" =>
                    ["class"     => Service1::class,
                     "arguments" => ["param1" => "another",
                                     "param2" => "test",
                                     "param3" => 1]],
                "aliasService2"  =>
                    ["class"     => Service2::class,
                     "arguments" => ["param3" => false,
                                     "param1" => "test"]],
                "aliasServiceX"  =>
                    ["class"     => Service2::class,
                     "arguments" => ["param3" => false,
                                     "param1" => "test",
                                     "param2" => "@aliasService1X"]],
            ];
    }

    public static function getServiceContainer(): ServiceContainer
    {
        $serviceContainer = new ServiceContainer();

        foreach (ServiceContainerTest::getConfig() as $alias => $serviceConf) {
            $service = new Service($serviceConf['class'], $alias);

            foreach ($serviceConf['arguments'] ?? [] as $argName => $argValue) {
                $service->addArgument($argName, $argValue);
            }

            foreach ($serviceConf['calls'] ?? [] as $call) {
                $service->addCall($call['method'], $call['arguments'] ?? []);
            }

            $serviceContainer->add($service);
        }

        return $serviceContainer;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testRegisterObjectAsService()
    {
        $serviceContainer = new ServiceContainer;
        $service = new Service($serviceObj1 = new Service1('test', 'test2', 1), 'alias1');
        $serviceContainer->add($service);

        $this->assertTrue($serviceContainer->has('alias1'));
        $this->assertEquals($serviceObj1, $serviceContainer->get('alias1'));
        $this->assertTrue($serviceContainer->has('alias1'));
        $this->assertFalse($serviceContainer->has('alias3'));
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testRegisterService()
    {
        $config = $this->getConfig();

        $serviceContainer = new ServiceContainer;
        $serviceContainer->add($service = new Service($config['aliasService1']['class'], 'service'));

        foreach ($config['aliasService1']['arguments'] ?? [] as $argName => $argValue) {
            $service->addArgument($argName, $argValue);
        }

        $this->assertInstanceOf('\Berlioz\ServiceContainer\Tests\files\Service1', $serviceObj = $serviceContainer->get('service'));
        $this->assertEquals($serviceObj, $serviceContainer->get('service'));
        $this->assertTrue($serviceContainer->has('service'));
    }

    public function testCallsService()
    {
        $serviceContainer = self::getServiceContainer();
        $serviceObj1 = $serviceContainer->get('aliasService1');
        $this->assertEquals(11, $serviceObj1->getParam3());
    }

    public function testRecursivelyServices()
    {
        $serviceContainer = self::getServiceContainer();
        $serviceObj1 = $serviceContainer->get(Service1::class);
        $serviceObj1X = $serviceContainer->get('aliasService1X');
        $serviceObjX = $serviceContainer->get('aliasServiceX');

        $this->assertNotEquals($serviceObj1, $serviceObjX->getParam2());
        $this->assertEquals($serviceObj1X, $serviceObjX->getParam2());
    }

    public function testNotReferencedService()
    {
        $serviceContainer = self::getServiceContainer();
        $serviceObj = $serviceContainer->get(Service5::class);

        $this->assertInstanceOf(Service5::class, $serviceObj);
    }

    public function testNotReferencedServiceWithDependencies()
    {
        $serviceContainer = self::getServiceContainer();

        $serviceObj = $serviceContainer->get(Service6::class);
        $this->assertInstanceOf(Service6::class, $serviceObj);

        $serviceObj = $serviceContainer->get(Service7::class);
        $this->assertInstanceOf(Service7::class, $serviceObj);
    }

    public function testNotReferencedServiceWithDependenciesUnresolvable()
    {
        $this->expectException(InstantiatorException::class);
        $serviceContainer = self::getServiceContainer();
        $serviceContainer->get(Service8::class);
    }

    public function testServiceFactory()
    {
        $serviceContainer = new ServiceContainer;
        $service = new Service(Service1::class);
        $service->setFactory(ServiceFactory::class . '::' . 'service1');
        $serviceContainer->add($service);

        /** @var Service1 $serviceObj1 */
        $serviceObj1 = $serviceContainer->get(Service1::class);

        $this->assertEquals('foo', $serviceObj1->getParam1());
        $this->assertEquals(2, $serviceObj1->getParam3());
    }

    public function testSerialization()
    {
        $serviceContainer = self::getServiceContainer();

        $serviceObj1 = $serviceContainer->get(Service1::class);

        $serviceContainer = serialize($serviceContainer);
        $serviceContainer = unserialize($serviceContainer);

        $serviceObj1bis = $serviceContainer->get(Service1::class);
        $serviceObj1ter = $serviceContainer->get(Service1::class);

        $this->assertNotSame($serviceObj1, $serviceObj1bis);
        $this->assertSame($serviceObj1bis, $serviceObj1ter);
    }
}
