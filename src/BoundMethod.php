<?php declare(strict_types=1);

namespace Jeekens\Container;

use Closure;
use Jeekens\Container\Exception\BindingResolutionException;
use Jeekens\Container\Exception\EntryNotFoundException;
use Jeekens\Container\Exception\NotInstantiableException;
use ReflectionMethod;
use ReflectionFunction;
use InvalidArgumentException;

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
    public static function call(Container $container, $callback, array $parameters = [], $defaultMethod = null)
    {
        if (static::isCallableWithAtSign($callback) || $defaultMethod) {
            return static::callClass($container, $callback, $parameters, $defaultMethod);
        }

        return static::callBoundMethod($container, $callback, function () use ($container, $callback, $parameters) {
            return call_user_func_array(
                $callback, static::getMethodDependencies($container, $callback, $parameters)
            );
        });
    }

    /**
     * callClass
     *
     * @param Container $container
     * @param $target
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
    protected static function callClass(Container $container, $target, array $parameters = [], $defaultMethod = null)
    {
        $segments = explode('@', $target);

        // We will assume an @ sign is used to delimit the class name from the method
        // name. We will split on this @ sign and then build a callable array that
        // we can pass right back into the "call" method for dependency binding.
        $method = count($segments) === 2
            ? $segments[1] : $defaultMethod;

        if (is_null($method)) {
            throw new InvalidArgumentException('Method not provided.');
        }

        return static::call(
            $container, [$container->make($segments[0]), $method], $parameters
        );
    }

    /**
     * callBoundMethod
     *
     * @param Container $container
     * @param $callback
     * @param $default
     *
     * @return mixed
     */
    protected static function callBoundMethod(Container $container, $callback, $default)
    {
        if (! is_array($callback)) {
            return $default instanceof Closure ? $default() : $default;
        }

        // Here we need to turn the array callable into a Class@method string we can use to
        // examine the container and see if there are any method bindings for this given
        // method. If there are, we can call this method binding callback immediately.
        $method = static::normalizeMethod($callback);

        if ($container->hasMethodBinding($method)) {
            return $container->callMethodBinding($method, $callback[0]);
        }

        return $default instanceof Closure ? $default() : $default;
    }

    /**
     * Normalize the given callback into a Class@method string.
     *
     * @param  callable  $callback
     *
     * @return string
     */
    protected static function normalizeMethod($callback)
    {
        $class = is_string($callback[0]) ? $callback[0] : get_class($callback[0]);

        return "{$class}@{$callback[1]}";
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

    /**
     * Determine if the given string is in Class@method syntax.
     *
     * @param  mixed  $callback
     *
     * @return bool
     */
    protected static function isCallableWithAtSign($callback)
    {
        return is_string($callback) && strpos($callback, '@') !== false;
    }
}
