<?php declare(strict_types=1);

namespace Jeekens\Container;


use function is_array;

class ContextualBindingBuilder implements ContextualBindingBuilderInterface
{
    /**
     * 容器
     *
     * @var Container
     */
    protected $container;

    /**
     * 实例
     *
     * @var string | array
     */
    protected $concrete;

    /**
     * 抽象目标
     *
     * @var string
     */
    protected $needs;

    /**
     * ContextualBindingBuilder constructor.
     *
     * @param Container $container
     * @param $concrete
     */
    public function __construct(Container $container, $concrete)
    {
        $this->concrete = $concrete;
        $this->container = $container;
    }

    /**
     * @param $abstract
     *
     * @return $this|mixed
     */
    public function needs($abstract)
    {
        $this->needs = $abstract;
        return $this;
    }

    /**
     * @param $implementation
     *
     * @return mixed|void
     */
    public function give($implementation)
    {
        $concretes = $this->concrete;

        if ($concretes == null) {
            $concretes = [];
        } elseif (! is_array($concretes)) {
            $concretes = [$concretes];
        }

        foreach ($concretes as $concrete) {
            $this->container->addContextualBinding($concrete, $this->needs, $implementation);
        }
    }
}