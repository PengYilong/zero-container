<?php
namespace zero;

use ArrayAccess;
use ReflectionClass;
use ReflectionException;
use InvalidArgumentException;
use Countable;
use zero\exception\ClassNotFoundException;

class Container implements ArrayAccess, Countable{

    /**
     * @var container
     */
    protected static $instance;

    /**
     * the classes instantiated
     * @var array
     */
    public $instances = [];

    protected $bind = [
        'application' => Application::class,
        'config' => Config::class,
        'env' => Env::class,
        'request' => Request::class,
        'session' => Session::class,
        'route' => Route::class,
        'middleware' => Middleware::class,
        'hook' => Hook::class,
    ];

    private function __construct()
    {
    }

    /**
     * get current instance
     */
    public static function getInstance()
    {
        if( null === static::$instance ){
            static::$instance = new static;
        }   
        return static::$instance;
    }

    /**
     * static method for the function make  
     */
    public static function get($class, $args = [], $newInstance = false)
    {
       return static::getInstance()->make($class, $args, $newInstance);
    }

    /**
     * make a class instantiated
     * @access public
     * @param  string $class the name of the class
     * @param  boolean|array  the args of the __cnostruct() function of the class
     * @param  boolean whether the class always is instantiated
     * @return object new instance 
     */
    public function make(string $class, $args = [], bool $newInstance = false)
    {
        if( true == $args ){
            $newInstance = true;
            $args = [];
        }

        $realClass = $this->bind[$class] ?? $class;
        
        if( isset($this->instances[$realClass]) && !$newInstance ){
            return $this->instances[$realClass]; 
        }

        $object = $this->invokeClass($realClass, $args);

        if(!$newInstance){
            $this->instances[$realClass] = $object;
        }

        return $object;
    }

    public function invokeClass(string $class, array $args = [])
    {
        try {
            $ref = new ReflectionClass($class);
            $constructor = $ref->getConstructor();

            if( $constructor ){
                $realArgs = $this->bindParams($constructor, $args);
                $object = $ref->newInstanceArgs($realArgs);
            } else {
                $object = $ref->newInstance();
            }

            return $object;
        } catch (ReflectionException $e) {
            throw new ClassNotFoundException('Class Not Found:'. $e->getMessage(), $class);
        }
    }

    public function bindParams($constructor, $args = [])
    {
        $params = $constructor->getParameters();
        if( !empty( $params ) ){
            foreach($params as $value ){
                $name = $value->getName();
                if( isset($args[$name]) ){
                    $realArgs[] = $args[$name];
                } else if( $value->getClass() ){
                    $realArgs[] = $this->make($value->getClass()->getName()); 
                } else if( $value->isDefaultValueAvailable() ){
                    $realArgs[] = $value->getDefaultValue();
                } else {
                    throw new InvalidArgumentException('The param of the method is missed:'. $value->getName());
                } 
            }
        } else {
            $realArgs = [];
        }
        return $realArgs;
    }

    public function invokeReflectMoethod($instance, $reflectMethod, $args = [])
    {
        $args = $this->bindParams($reflectMethod, $args);
        return $reflectMethod->invokeArgs($instance, $args);
    }

    public static function factory(string $name, string $namespace = '', array $args = [])
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);

        return Container::getInstance()->invokeClass($class, $args);
    }

    /**
     * get current instance
     */
    public static function setInstance($instance)
    {
        static::$instance = $instance;
    } 

    public function offsetExists ( $offset ) : bool 
    {
        return isset($this->instances[$offset]);
    }

    public function offsetGet( $offset )
    {
        return $this->__get( $offset );
    } 

    public function offsetSet( $offset, $value) : void
    {
        if( is_null($offset) ){
            $this->instances[] = $value;
        } else {
            $this->instances[$offset] = $value;
        }
    } 

    public function offsetUnset( $offset ) : void
    {
        unset($this->instances[$offset]); 
    } 

    public function count()
    {
        return count($this->instances);
    }

    public function __get($class)
    {  
        return static::get($class);
    }
}