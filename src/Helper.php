<?php declare(strict_types=1);

use Jeekens\Container\Container;

if (!function_exists('target')) {
    /**
     * 从容器中取出目标服务
     *
     * @param string $abstract
     * @param array $parameters
     *
     * @return mixed|object
     *
     * @throws ReflectionException
     * @throws \Jeekens\Container\Exception\BindingResolutionException
     * @throws \Jeekens\Container\Exception\EntryNotFoundException
     * @throws \Jeekens\Container\Exception\NotInstantiableException
     */
    function target(string $abstract, array $parameters = [])
    {
        return Container::getInstance()->make($abstract, $parameters);
    }
}

if (!function_exists('injection')) {
    /**
     * 向容器中注入一个服务
     *
     * @param string $abstract
     * @param null $concrete
     * @param bool $shared
     *
     * @throws ReflectionException
     * @throws \Jeekens\Container\Exception\BindingResolutionException
     * @throws \Jeekens\Container\Exception\EntryNotFoundException
     * @throws \Jeekens\Container\Exception\NotInstantiableException
     */
    function injection(string $abstract, $concrete = null, $shared = false)
    {
        Container::getInstance()->bind($abstract, $concrete, $shared);
    }
}

if (!function_exists('injection_share')) {
    /**
     * 向容器中注入一个共享服务(单例)
     *
     * @param string $abstract
     * @param null $concrete
     * @param bool $shared
     *
     * @throws ReflectionException
     * @throws \Jeekens\Container\Exception\BindingResolutionException
     * @throws \Jeekens\Container\Exception\EntryNotFoundException
     * @throws \Jeekens\Container\Exception\NotInstantiableException
     */
    function injection_share(string $abstract, $concrete = null)
    {
        Container::getInstance()->bind($abstract, $concrete, true);
    }
}