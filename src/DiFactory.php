<?php
/**
 * How to use
 * In the controller that uses this for invoking.
 *
 * Add a property to the controller, add a setter for this property and annotate the setter with the
 * dependency that you want injected from the service manager eg.
 *
 *  we have an 'internal' database config in the service manager
 * to inject this into the controller, we would do this
 *
 * protected $internal; //class property
 *
 * @inject internal
 * public function setInternal(Adapter $internal)
 * {
 *      $this->internal = $internal;
 *      return $this;
 * }
 *
 */

namespace OrmX;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

final class DiFactory implements FactoryInterface
{
    
    private static $container;

    public static function create(string $class)
    {
        $controller = new $class();

        $reflector  = new \ReflectionClass($controller);
        $properties = $reflector->getProperties();

        /** @var \ReflectionProperty $property */
        foreach ($properties as $property) {
            //find the setter and read the doc block for @inject
            $setter = Util::makeSetter($property->getName());
            if ($reflector->hasMethod($setter)) {
                $method       = $reflector->getMethod($setter);
                $comment      = $method->getDocComment();
                $dependencies = [];
                \preg_match_all('/.*@inject (\S+)/', $comment, $dependencies);
                if (\count($dependencies) === 2) {
                    $dependencies = $dependencies[1];
                    foreach ($dependencies as $depenency) {
                        $dependancy = \trim($depenency);
                        $controller->{$setter}(static::$container->get($dependancy));
                    }
                }
            }
        }

        return $controller;
    }
    
    
    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return mixed|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        static::$container = $container;

        $controller = (null === $options) ? new $requestedName : new $requestedName($options);

        $reflector = new \ReflectionClass($controller);
        $properties = $reflector->getProperties();

        /** @var \ReflectionProperty $property */
        foreach ($properties as $property) {
            if ($property->getDeclaringClass()
                         ->getName() === $requestedName) {
                //find the setter and read the doc block for @inject
                $setter = Util::makeSetter($property->getName());
                if ($reflector->hasMethod($setter)) {
                    $method = $reflector->getMethod($setter);
                    $comment = $method->getDocComment();
                    $dependencies = [];
                    \preg_match('/.*@inject (\S+)/', $comment, $dependencies);
                    if (\count($dependencies) === 2) {
                        $dependancy = \trim(\array_pop($dependencies));
                        $controller->{$setter}($container->get($dependancy));
                    }
                }
            }
        }

        return $controller;
    }
}
