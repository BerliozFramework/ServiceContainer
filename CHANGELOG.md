# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [2.1.0] - In progress

### Added

- Compatibility with `psr/container` version 2
- "Nullable" services
- "@template" annotation for main container

## [2.0.3] - 2021-09-23

### Changed

- Move class `\Berlioz\ServiceContainer\Tests\Provider\ProviderTestCase` to `\Berlioz\ServiceContainer\Provider\ProviderTestCase`

## [2.0.2] - 2021-09-23

### Added

- Abstract test case class `\Berlioz\ServiceContainer\Tests\Provider\ProviderTestCase` to help test of providers

### Changed

- Reorder `ServiceProviderInterface` methods

### Fixed

- Infinite loop in `AutoWiringContainer` when trying to instantiate a recursive service

## [2.0.1] - 2021-09-09

### Fixed

- `Instantiator::getArguments()` tries to instantiate a class even if null is allowed

## [2.0.0] - 2021-09-08

No changes were introduced since the previous beta 4 release.

## [2.0.0-beta4] - 2021-06-07

### Changed

- Container provides himself for `\Psr\Container\ContainerInterface` interface

## [2.0.0-beta3] - 2021-04-14

### Added

- Can declare some provided classes by the service with method `Service::addProvide()`

### Changed

- Accept an array in first argument of `Container::call()`
- Exception message for missing service in arguments

### Removed

- Magic match of class in default container

### Fixed

- Union types not prioritized

## [2.0.0-beta2] - 2021-04-06

### Added

- Service providers

### Changed

- Check integrity of cache result
- Set the "object" property of service if gotten from cache

### Fixed

- Fixed `Instantiator::call()` on static method

## [2.0.0-beta1] - 2021-03-29

### Added

- Delegation of containers
- `CacheStrategy` class to manage services in cache
- `Inflector` class to add inflector capability

### Changed

- Bump to PHP 8 minimum compatibility
- Refactoring

## [1.1.0] - 2020-11-05

### Added

- PHP 8 compatibility
- Support of union types

## [1.0.1] - 2020-05-27

## Fixed

- Fixed call the same callback method after instantiation

## Changed

- Replace FQDN by import

## [1.0.0] - 2020-02-17

First version