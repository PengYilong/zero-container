<?php
namespace zero;

class Facade 
{

    protected static function createFacade()
    {
        $class = static::getFacadeClass();
        return Container::get($class);
    }

    public static function __callStatic($method, $arguments)
    {
        return call_user_func_array ( [static::createFacade(), $method], $arguments );
    }
}