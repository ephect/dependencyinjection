<?php

namespace Ephect\DependencyInjection;

class DIReflectionClass extends \ReflectionClass
{
    
    function manualClone() {
        return new DIReflectionClass($this->getName());
    }
    
}