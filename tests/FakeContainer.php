<?php

namespace Berlioz\ServiceContainer\Tests;

use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Inflector\Inflector;
use Berlioz\ServiceContainer\Instantiator;
use Generator;

class FakeContainer extends Container
{
    public function getContainers(): Generator
    {
        return parent::getContainers();
    }

    /**
     * @return Inflector[]
     */
    public function getInflectors(): array
    {
        return $this->inflectors;
    }

    /**
     * @return Instantiator
     */
    public function getInstantiator(): Instantiator
    {
        return $this->instantiator;
    }
}