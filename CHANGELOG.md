# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [2.0.0-beta4] - In progress

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