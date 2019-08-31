<?php declare(strict_types=1);

namespace Jeekens\Container;

use Jeekens\Container\Exception\BindingResolutionException;
use Jeekens\Container\Exception\EntryNotFoundException;
use Jeekens\Container\Exception\NotInstantiableException;
use ReflectionMethod;
use ReflectionFunction;

/**
 * Class BoundMethod
 *
 * @package Jeekens\Container
 */
class BoundMethod
{
    /**
     * call
     *
     * @param Container $container
     * @param $callback
     * @param array $parameters
     * @param null $defaultMethod
     *
     * @return mixed
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public static function call(Container $container, $callback, array $parameters = [])
    {
        if (static::isClassNameWithNonStaticMethod($callback)) {
            $callback[0] = $container->make($callback[0]);
        }

        return call_user_func_array(
            $callback, static::getMethodDependencies($container, $callback, $parameters)
        );
    }

    /**
     * @param callable $caller
     *
     * @return bool
     *
     * @throws \ReflectionException
     */
    public static function isClassNameWithNonStaticMethod(callable $caller) : bool
    {
        if (is_array($caller) && is_string($caller[0])) {
            $ref = new ReflectionMethod($caller[0], $caller[1]);
            return ! $ref->isStatic();
        }
        return false;
    }


    /**
     * getMethodDependencies
     *
     * @param Container $container
     * @param $callback
     * @param array $parameters
     *
     * @return array
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    protected static function getMethodDependencies(Container $container, $callback, array $parameters = [])
    {
        $dependencies = [];

        foreach (static::getCallReflector($callback)->getParameters() as $parameter) {
            static::addDependencyForCallParameter($container, $parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, $parameters);
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param  callable|string $callback
     *
     * @return \ReflectionFunctionAbstract
     *
     * @throws \ReflectionException
     */
    protected static function getCallReflector($callback)
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback);
        }

        return is_array($callback)
            ? new ReflectionMethod($callback[0], $callback[1])
            : new ReflectionFunction($callback);
    }

    /**
     * addDependencyForCallParameter
     *
     * @param Container $container
     * @param \ReflectionParameter $parameter
     * @param array $parameters
     * @param $dependencies
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    protected static function addDependencyForCallParameter(Container $container, $parameter,
        array &$parameters, &$dependencies)
    {
        if (array_key_exists($parameter->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->name];

            unset($parameters[$parameter->name]);
        } elseif ($parameter->getClass() && array_key_exists($parameter->getClass()->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->getClass()->name];

            unset($parameters[$parameter->getClass()->name]);
        } elseif ($parameter->getClass()) {
            $dependencies[] = $container->make($parameter->getClass()->name);
        } elseif ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        }
    }

}
