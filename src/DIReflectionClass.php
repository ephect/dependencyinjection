<?php

namespace Ephect\DependencyInjection;

class DIReflectionClass extends \ReflectionClass
{
    
    function manualClone() 
    {
        return new DIReflectionClass($this->getName());
    }
    
    function formatParameters($args, $method=null)
    {
        
        if (is_null($method)) {
            
        } else {
            
        }
        
        /*
         * 
         * Needs to be re-written to work inside the ReflectionClass
         * 
         */
        
        // We have a value
        if (isset($this->args[$name][$param])) {
            // Assign the value to the correct position in the constructor
            $args[$paramPos] = $this->args[$name][$param];

            // If the value is a valid service name, inject that
            if (isset($this->reflectors[$args[$paramPos]])) {
                // Use the $this->get() recursively to ensure that it is also loaded
                $args[$paramPos] = $this->get($args[$paramPos]);
            }

        // No value to use. Is there a default constant?
        } elseif ($param->isDefaultValueConstant()) {
            // Set that constant
            $args[$paramPos] = $param->getDefaultValueConstantName();

        // No value to use. Is there a default value?
        } elseif ($param->isDefaultValueAvailable()) {
            // Set that value
            $args[$paramPos] = $param->getDefaultValue();
        } else {
            // No other options... just set it to null
            $args[$paramPos] = null;
        }
        
        return $args;
        
    }
    
}