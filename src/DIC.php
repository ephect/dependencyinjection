<?php

namespace Ephect\DependencyInjection;

class DIC
{
    
    protected $reflectors = array();
    
    protected $loaded = array();
    
    protected $args = array();
    
    protected $compiledArgs;
    
    function __construct()
    {
        
        $this->compiledArgs =  function($name, $param, $paramPos) {
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
        };
        
    }
    
    /*
     * Registers a class for use in the DIC
     */
    function register($name, $class, $args = array(), $forceLoad = false)
    {
        
        // Trying to register a name that's already registered
        if (isset($this->reflectors[$name])) {
            throw new \Exception("Cannot re-register class in Dependency Injection Container: ".$name);
        }
        
        // Load into the reflector
        $this->reflectors[$name] = new DIReflectionClass($class);
        $this->args[$name] = $args;
        
        // If we want to load the class right now, do it
        if ($forceLoad) {
            return $this->get($name);
        } else {
            return $this;
        }
        
    }
    
    /*
     * Wraps a register in a closer so that the ReflectionClass isn't created until
     * it is needed. To be a true lazy-load, the registered class must also be a
     * string instead of an instance.
     */
    function lazyRegister($name, $class, $args = array())
    {
        
        // Trying to register a name that's already registered
        if (isset($this->reflectors[$name])) {
            throw new \Exception("Cannot re-register class in Dependency Injection Container: ".$name);
        }
        
        // Only strings of the class name (full namespace) can be lazy-loaded
        if (is_string($class)) {
            $this->reflectors[$name] = 
                function() use ($class)
                {
                    return new DIReflectionClass($class);
                };
                
            $this->args[$name] = $args;
        }
        
        return $this;
        
    }
    
    /*
     * Returns the registered class, loading it first it hasn't been loaded yet.
     */
    function get($name)
    {
        
        // If it's been loaded, exit fast
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }
        
        // If it's not registered, we can't load it
        if (!isset($this->reflectors[$name])) {
            throw new \Exception("Cannot retrieve uninjected class: ".$name);
        }
        
        
        // Take care of lazy-loads
        $obj = $this->getReflector($name);

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

            // Get args from closure
            $args = $this->compiledArgs($name, $param->getName(), $param->getPosition());
        }

        // Make absolutely sure that the args are sorted in the correct position order
        ksort($args);

        // Make a new instance of the target class, calling the construct with args
        $finalObj = $obj->newInstanceArgs($args);

        // Fill the loaded variable for later use with the spiffy new class
        $this->loaded[$name] = $finalObj;

        // unset the args
        unset($this->args[$name]);

        // Return our completed object
        return $finalObj;
            
    }
    
    /*
     * Force a replacement of the args for a given class.
     */
    function forceArgs($name, $args = array())
    {
        
        // Add/overwrite args for $name
        $this->args[$name] = $args;
        
        // Allow this method to be chained
        return $this;
        
    }
    
    /*
     * Force the get() method to reload a loaded class. This is, as the name suggests,
     * destructive. The state of the existing loaded class will be completely lost.
     */
    function forceDestructiveGet($name)
    {
        
        // Reset the loaded class
        unset($this->loaded[$name]);
        
        // Perform the load again
        return $this->get($name);
        
    }
    
    /*
     * Forces a new, empty state of the class to be returned while preserving the
     * existing state within the container.
     */
    function forceSingleGet($name)
    {
        
        // Store existing class temporarily
        $tObj = $this->loaded[$name];
        
        // For the get
        $single = $this->forceDestructiveGet($name);
        
        // Restore the old class
        $this->loaded[$name] = $tObj;
        
        // Return the generated class
        return $single;
        
    }
    
    /*
     * Make a new instance of the $name ReflectionClass using $newName, and if present
     * give the new instance the $args.
     */
    function cloneRegister($name, $newName, $args = array())
    {
        
        if (isset($this->reflectors[$newName])) {
            throw new \Exception("Cannot register clone ".$newName.": class already registered.");
        }
        
        if (!isset($this->reflectors[$name])) {
            throw new \Exception("Cannot register clone ".$newName.": non-existent class ".$name);
        }
        
        $this->reflectors[$newName] = $this->getReflector($name)->manualClone();
        
        if (count($args)) {
            $this->args[$newName] = $args;
        } elseif (isset($this->args[$name])) {
            $this->args[$newName] = $args;
        } else {
            $this->args[$newName] = array();
        }
        
        return $this;
        
    }
    
    /*
     * Alias for $dic->cloneRegister()->get()
     */
    function cloneRegisterAndGet($name, $newName, $args = array())
    {
        
        return $this->cloneRegister($name, $newName, $args)->get($newName);
        
    }
    
    /*
     * Silently return the ReflectionClass, whether or not it was lazy-loaded
     */
    protected function getReflector($name)
    {
        
        if (is_callable($this->reflectors[$name])) {
            $obj = $this->register[$name]();
            $this->reflectors[$name] = $obj;
            return $obj;
        } else {
            return $this->reflectors[$name];
        }
        
    }
    
}