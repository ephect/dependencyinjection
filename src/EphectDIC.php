<?php

namespace Ephect\DependencyInjection;

final class EphectDIC extends DIC
{
    
    protected $active = true;
    
    public function deactivate() 
    {
        
        $this->active = false;
        
    }
    
    public function register($name, $class, $args = array(), $forceLoad = false) {
        if (!$this->active) {
            throw new \Exception("Cannot alter the Dependency Injection Container in this context.");
        }
        
        return parent::register($name, $class, $args, $forceLoad);
    }
    
    public function lazyRegister($name, $class, $args = array()) {
        if (!$this->active) {
            throw new \Exception("Cannot alter the Dependency Injection Container in this context.");
        }
        
        return parent::lazyRegister($name, $class, $args);
    }
    
    public function forceDestructiveGet($name) {
        if (!$this->active) {
            throw new \Exception("Cannot alter the Dependency Injection Container in this context.");
        }
        
        return parent::forceDestructiveGet($name);
    }
    
    public function forceArgs($name, $args = array()) {
        if (!$this->active) {
            throw new \Exception("Cannot alter the Dependency Injection Container in this context.");
        }
        
        return parent::forceArgs($name, $args);
    }
    
}