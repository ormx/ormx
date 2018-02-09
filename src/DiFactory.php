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

namespace Application\Util;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

final class DiFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
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
                    preg_match('/.*@inject (\S+)/', $comment, $dependencies);
                    if (\count($dependencies) === 2) {
                        $dependancy = trim(array_pop($dependencies));
                        $controller->{$setter}($container->get($dependancy));
                    }
                }
            }
        }

        return $controller;
    }
}
