<?php

namespace Jeekens\Container;

use Closure;
use Jeekens\Container\Exception\EntryNotFoundException;
use Psr\Container\ContainerInterface as BaseContainerInterface;

interface ContainerInterface extends BaseContainerInterface
{
    /**
     * 判断是否绑定了指定的服务抽象类型
     *
     * @param  string  $abstract
     *
     * @return bool
     */
    public function bound(string $abstract);

    /**
     * 给指定类型的服务取个别名
     *
     * @param  string  $abstract
     * @param  string  $alias
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function alias(string $abstract, string $alias);

    /**
     * 注册一个服务到容器
     *
     * @param  string  $abstract
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared
     *
     * @return void
     */
    public function bind(string $abstract, $concrete = null, $shared = false);

    /**
     * 当一个服务不存在时则，注册一个服务到容器
     *
     * @param  string  $abstract
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared
     *
     * @return void
     */
    public function bindIf($abstract, $concrete = null, $shared = false);

    /**
     * 注册一个共享服务
     *
     * @param  string  $abstract
     * @param  \Closure|string|null  $concrete
     *
     * @return void
     */
    public function singleton(string $abstract, $concrete = null);

    /**
     * 设置一个绑定上下文
     *
     * @param  string|array  $concrete
     *
     * @return ContextualBindingBuilderInterface
     */
    public function when($concrete);

    /**
     * 刷新所有服务和已解析的容器实例
     */

    public function flush();

    /**
     * 通过容器解析指定服务
     *
     * @param  string  $abstract
     * @param  array  $parameters
     *
     * @return mixed
     *
     * @throws EntryNotFoundException
     */
    public function make(string $abstract, array $parameters = []);

    /**
     * 调用闭包或回调，并解决闭包依赖问题
     *
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     *
     * @return mixed
     */
    public function call($callback, array $parameters = [], $defaultMethod = null);

    /**
     * 扩展容器中的抽象
     *
     * @param string $abstract
     * @param Closure $closure
     *
     * @return mixed
     */
    public function extend($abstract, Closure $closure);

    /**
     * 是否为别名
     *
     * @param string $name
     *
     * @return mixed
     */
    public function isAlias(string $name);

    /**
     * 判断抽象是否解析
     *
     * @param string $abstract
     *
     * @return mixed
     */
    public function resolved($abstract);

    /**
     * 注册解析时执行的回调
     *
     * @param string $abstract
     * @param Closure|null $callback
     *
     * @return mixed
     */
    public function resolving($abstract, Closure $callback = null);

    /**
     * 注册在首次解析抽象时解析操作之后执行的回调
     *
     * @param string $abstract
     * @param Closure|null $callback
     *
     * @return mixed
     */
    public function afterResolving($abstract, Closure $callback = null);

    /**
     * 添加上下文绑定
     *
     * @param string $concrete
     * @param string $abstract
     * @param \Closure|string $implementation
     *
     * @return mixed
     */
    public function addContextualBinding($concrete, $abstract, $implementation);
}