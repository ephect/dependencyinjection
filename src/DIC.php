<?php

namespace Ephect\DependencyInjection;

class DIC
{
    
    protected $register = array();
    
    protected $loaded = array();
    
    protected $args = array();
    
    function register($name, $class, $args = array(), $forceLoad = false)
    {
        
        // Trying to register a name that's already registered
        if (isset($this->register[$name])) {
            throw new \Exception("Cannot re-register class in Dependency Injection Container: ".$name);
        }
        
        // Load into the reflector
        $this->register[$name] = new DIReflectionClass($class);
        $this->args[$name] = $args;
        
        // If we want to load the class right now, do it
        if ($forceLoad) {
            $this->get($name);
        }
        
    }
    
    function lazyRegister($name, $class, $args = array())
    {
        
        // Trying to register a name that's already registered
        if (isset($this->register[$name])) {
            throw new \Exception("Cannot re-register class in Dependency Injection Container: ".$name);
        }
        
        // Only strings of the class name (full namespace) can be lazy-loaded
        if (is_string($class)) {
            $this->register[$name] = 
                function() use ($class)
                {
                    return new DIReflectionClass($class);
                };
                
            $this->args[$name] = $args;
        }
        
    }
    
    function get($name)
    {
        
        // If it's been loaded, exit fast
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }
        
        // If it's not registered, we can't load it
        if (!isset($this->register[$name])) {
            throw new \Exception("Cannot retrieve uninjected class: ".$name);
        }
        
        // If it's still lazy, wake it up
        if (is_callable($this->register[$name])) {
            // Call the anonymous function
            $obj = $this->register[$name]();
            
            // Reset the register
            $this->register[$name] = $obj;
        }
        
        // At this point, if it isn't an object something is very strange
        if (is_object($this->register[$name])) {
            // Reassign $obj again, in case this isn't a lazy-load
            $obj = $this->register[$name];
            
            // Get the constructor of the class
            $constructor = $obj->getConstructor();
            
            // And get the parameters of the constructor
            $params = $constructor->getParameters();
            
            // Loop over the params
            foreach ($params as $param) {
                // If the param is required, but we don't have a value to use, panic
                if (!$param->isOptional && !isset($this->args[$name][$param->getName()])) {
                    throw new \Exception("Cannot warm up ".$obj->getName()." (".$obj->getFileName().") because required parameter ".$param->getName()." was not available.");
                }
                
                // We have a value
                if (isset($this->args[$name][$param->getName()])) {
                    // Assign the value to the correct position in the constructor
                    $args[$param->getPosition()] = $this->args[$name][$param->getName()];
                    
                    // If the value is a valid service name, inject that
                    if (isset($this->register[$args[$param->getPosition()]])) {
                        // Use the $this->get() recursively to ensure that it is also loaded
                        $args[$param->getPosition()] = $this->get($args[$param->getPosition()]);
                    }
                    
                // No value to use. Is there a default constant?
                } elseif ($param->isDefaultValueConstant()) {
                    // Set that constant
                    $args[$param->getPosition()] = $param->getDefaultValueConstantName();
                    
                // No value to use. Is there a default value?
                } elseif ($param->isDefaultValueAvailable()) {
                    // Set that value
                    $args[$param->getPosition()] = $param->getDefaultValue();
                } else {
                    // No other options... just set it to null
                    $args[$param->getPosition()] = null;
                }
            }
            
            // Make absolutely sure that the args are sorted in the correct position order
            ksort($args);
            
            // Make a new instance of the target class, calling the construct with args
            $finalObj = $obj->newInstanceArgs($args);
            
            // Fill the loaded variable for later use with the spiffy new class
            $this->loaded[$name] &= $finalObj;
            
            // unset the args
            unset($this->args[$name]);
            
            // Return our completed object
            return $finalObj;
        }
        
        // We got to here, which should NEVER happen
        throw new \Exception("Unknown value for '".$name."'. Expected: Object; Actual: ".gettype($this->register[$name]));
        
    }
    
}