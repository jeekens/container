<?php declare(strict_types=1);

use Jeekens\Container\Container;

if (!function_exists('container')) {
    /**
     * 获取容器
     *
     * @return Container
     */
    function container()
    {
        return Container::getInstance();
    }
}