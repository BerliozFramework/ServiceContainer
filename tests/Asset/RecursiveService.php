<?php

namespace Berlioz\ServiceContainer\Tests\Asset;

class RecursiveService
{
    public function __construct(RecursiveService $service)
    {
    }
}