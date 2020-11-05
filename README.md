# Berlioz Service Container

[![Latest Version](https://img.shields.io/packagist/v/berlioz/service-container.svg?style=flat-square)](https://github.com/BerliozFramework/ServiceContainer/releases)
[![Software license](https://img.shields.io/github/license/BerliozFramework/ServiceContainer.svg?style=flat-square)](https://github.com/BerliozFramework/ServiceContainer/blob/master/LICENSE)
[![Build Status](https://img.shields.io/travis/com/BerliozFramework/ServiceContainer/master.svg?style=flat-square)](https://travis-ci.com/BerliozFramework/ServiceContainer)
[![Quality Grade](https://img.shields.io/codacy/grade/cb21d20358cc4ba2be5ab42bf0ddb8b2/master.svg?style=flat-square)](https://www.codacy.com/manual/BerliozFramework/ServiceContainer)
[![Total Downloads](https://img.shields.io/packagist/dt/berlioz/service-container.svg?style=flat-square)](https://packagist.org/packages/berlioz/service-container)

**Berlioz Service Container** is a PHP library to manage your services with dependencies injection, respecting PSR-11 (Container interface) standard.

For more information, and use of Berlioz Framework, go to website and online documentation :
https://getberlioz.com

## Installation

### Composer

You can install **Berlioz Service Container** with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require berlioz/service-container
```

### Dependencies

* **PHP** ^7.1 || ^8.0
* Packages:
  * **psr/container**


## Usage

### Service container

Methods available more than :

- `get($id)`

  > PSR-11: Finds an entry of the container by its identifier and returns it.
  > 
  > **Accept a class name directly.**

- `has($id)`

  > PSR-11: Returns true if the container can return an entry for the given identifier.
  > Returns false otherwise.

- `add(Service $service)`

  > Register a new service.

A Service object:

- `Service::__construct(string|object $class, ?string $alias = null)`

  > Constructor of Service object.

- `Service::addArgument(string $name, mixed $value): Service`

  > Add argument to make an instance of service class.

- `Service::addArguments(array $arguments): Service`

  > It's an array of arguments, the key must be the name of argument and the value of key, must be the argument value.

- `Service::addCall(string $method, array $arguments = []): Service`

  > Method name (and arguments) called just after the construction of object class.

- `Service::setFactory(string $factory): Service`

  > It's the factory static method used to make object.
  >
  > Example: `MyProject\Name\Space\MyFactory::service`

### Instantiator

New instance of a class or object:
```php
$instantiator = new Instantiator();
$object = $instantiator->newInstanceOf(MyClass::class,
                                       ['argument1' => 'Value',
                                        'argument3' => 'Value',
                                        'argument2' => 'Value']);
```

Invoke a method:
```php
$instantiator = new Instantiator();
$instantiator->invokeMethod($myObject,
                            'myMethodName',
                            ['argument1' => 'Value',
                             'argument3' => 'Value',
                             'argument2' => 'Value']);
```

Invoke a function:
```php
$instantiator = new Instantiator();
$instantiator->invokeFunction('myFunctionName',
                              ['argument1' => 'Value',
                               'argument3' => 'Value',
                               'argument2' => 'Value']);
```

In all examples cases, the last argument is an array of parameters to give to the constructor, method or function.
The order of arguments is not important.

In case of a parameter is an object, the system get the classes implemented to inject in good parameter.