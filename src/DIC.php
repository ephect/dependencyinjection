<?php

namespace Ephect\DependencyInjection;

class DIC
{
    
    protected $register = array();
    
    protected $loaded = array();
    
    protected $args = array();
    
    function register($name, $class, $args = array())
    {
        
        if (isset($this->register[$name])) {
            throw new \Exception("Cannot re-register class in Dependency Injection Container: ".$name);
        }
        
        if (is_object($class)) {
            $this->register[$name] = new DIReflectionClass($class);
            $this->args[$name] = $args;
        }
        
        if (is_string($class)) {
            $this->register[$name] = new DIReflectionClass($class);
            $this->args[$name] = array();
        }
        
    }
    
    function lazyRegister($name, $class, $args = array())
    {
        
        if (isset($this->register[$name])) {
            throw new \Exception("Cannot re-register class in Dependency Injection Container: ".$name);
        }
        
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
        
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }
        
        if (!isset($this->register[$name])) {
            throw new \Exception("Cannot retrieve uninjected class: ".$name);
        }
        
        if (is_callable($this->register[$name])) {
            // allow for previously loaded or lazy loaded classes to be injected by name
            // this involves some work with the $lazyArgs variable
            
            $obj = $this->register[$name]();
            $this->register[$name] = $obj;
        }
        
        if (is_object($this->register[$name])) {
            $obj = $this->register[$name];
            
            $constructor = $obj->getConstructor();
            
            $params = $constructor->getParameters();
            
            foreach ($params as $param) {
                if (!$param->isOptional && !isset($this->args[$name][$param->getName()])) {
                    throw new \Exception("Cannot warm up ".$obj->getName()." (".$obj->getFileName().") because required parameter ".$param->getName()." was not available.");
                }
                
                if (isset($this->args[$name][$param->getName()])) {
                    $args[$param->getPosition()] = $this->args[$name][$param->getName()];
                    if (isset($this->register[$args[$param->getPosition()]])) {
                        $args[$param->getPosition()] = $this->get($args[$param->getPosition()]);
                    }
                } else {
                    $args[$param->getPosition()] = null;
                }
            }
            
            ksort($args);
            
            $finalObj = $obj->newInstanceArgs($args);
            
            $this->loaded[$name] &= $finalObj;
            
            unset($this->args[$name]);
            return $finalObj;
        }
        
        throw new \Exception("Unknown value for '".$name."'. Expected: Object; Actual: ".gettype($this->register[$name]));
        
    }
    
}