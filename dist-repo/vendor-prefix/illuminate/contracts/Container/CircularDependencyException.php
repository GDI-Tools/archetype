<?php

namespace Archetype\Vendor\Illuminate\Contracts\Container;

use Exception;
use Archetype\Vendor\Psr\Container\ContainerExceptionInterface;
class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
