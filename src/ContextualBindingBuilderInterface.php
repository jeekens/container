<?php

namespace Jeekens\Container;


interface ContextualBindingBuilderInterface
{
    /**
     * 定义依赖上下文的抽象
     *
     * @param $abstract
     *
     * @return mixed
     */
    public function needs($abstract);

    /**
     * 定义上下文绑定的具体实现
     *
     * @param $implementation
     *
     * @return mixed
     */
    public function give($implementation);
}