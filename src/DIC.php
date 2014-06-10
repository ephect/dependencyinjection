<?php

namespace Ephect\DependencyInjection;

class DIC
{
    
    protected $register = array();
    
    protected $lazyArgs = array();
    
    function register($name, $class, $args = array())
    {
        
        if (isset($this->register[$name])) {
            throw new \Exception("Cannot re-register class in Dependency Injection Container: ".$name);
        }
        
        if (is_object($class)) {
            $this->register[$name] = $class;
        }
        
        if (is_string($class)) {
            $this->register[$name] = new $class($args);
        }
        
    }
    
    function lazyRegister($name, $class, $args = array())
    {
        
        if (isset($this->register[$name])) {
            throw new \Exception("Cannot re-register class in Dependency Injection Container: ".$name);
        }
        
        if (is_string($class)) {
            $this->register[$name] = 
                function($args) use ($class)
                {
                    return new $class($args);
                };
                
            $this->lazyArgs[$name] = $args;
        }
        
    }
    
    function get($name)
    {
        
        if (!isset($this->register[$name])) {
            throw new \Exception("Cannot retrieve uninjected class: ".$name);
        }
        
        if (is_callable($this->register[$name])) {
            // allow for previously loaded or lazy loaded classes to be injected by name
            // this involves some work with the $lazyArgs variable
            
            $obj = $this->register[$name]($this->lazyArgs[$name]);
            $this->register[$name] = $obj;
            
            unset($this->lazyArgs[$name]);
            return $obj;
        }
        
        if (is_object($this->register[$name])) {
            return $this->register[$name];
        }
        
        throw new \Exception("Unknown value for '".$name."'. Expected: Object; Actual: ".gettype($this->register[$name]));
        
    }
    
}