<?php declare(strict_types=1);

namespace Jeekens\Container;

use Closure;
use Exception;
use Jeekens\Container\Exception\BindingResolutionException;
use Jeekens\Container\Exception\EntryNotFoundException;
use Jeekens\Container\Exception\NotInstantiableException;
use LogicException;
use ReflectionClass;
use ReflectionParameter;
use function array_pop;
use function compact;
use function count;
use function end;
use function is_null;

/**
 * Class Container
 *
 * @package Jeekens\Container
 */
class Container implements ContainerInterface
{

    /**
     * @var static
     */
    protected static $_self;

    /**
     * 单例服务
     *
     * @var array
     */
    protected $instances = [];

    /**
     * 请求级别单例
     *
     * @var array
     */
    protected $requestInstances = [];

    /**
     * 服务原型
     *
     * @var array
     */
    protected $prototype = [];

    /**
     * 别名
     *
     * @var array
     */
    protected $aliases = [];

    /**
     * 绑定
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * 抽象类型已注册的别名
     *
     * @var array
     */
    protected $abstractAliases = [];

    /**
     * @var array
     */
    protected $buildStack = [];

    /**
     * 参数覆盖堆
     *
     * @var array
     */
    protected $with = [];

    /**
     * 绑定上下文
     *
     * @var array
     */
    protected $contextual = [];

    /**
     * 实例扩展信息
     *
     * @var array
     */
    protected $extenders = [];

    /**
     * Container constructor.
     */
    private function __construct()
    {
    }


    private function __clone()
    {
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (!self::$_self) {
            self::$_self = new self();
        }

        return self::$_self;
    }

    /**
     * get
     *
     * @param string $id
     *
     * @return mixed|object
     *
     * @throws EntryNotFoundException
     * @throws Exception
     */
    public function get($id)
    {
        try {

            return $this->resolve($id);

        } catch (Exception $e) {

            if ($this->has($id)) {
                throw $e;
            }

            throw new EntryNotFoundException;
        }
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function has($id)
    {
        return $this->bound($id);
    }

    /**
     * @param string $abstract
     * @param string $alias
     */
    public function alias(string $abstract, string $alias)
    {
        if ($alias === $abstract) {
            throw new LogicException("[{$abstract}] is aliased to itself.");
        }

        $this->aliases[$alias] = $abstract;

        $this->abstractAliases[$abstract][] = $alias;
    }

    /**
     * 获取抽象别名
     *
     * @param string $abstract
     *
     * @return mixed
     */
    public function getAlias(string $abstract)
    {
        if (!isset($this->aliases[$abstract])) {
            return $abstract;
        }

        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * @param string $abstract
     *
     * @return bool
     */
    public function bound(string $abstract)
    {
        return isset($this->bindings[$abstract]) ||
            isset($this->instances[$abstract]) ||
            $this->isAlias($abstract);
    }

    /**
     * @param string $name
     *
     * @return bool|mixed
     */
    public function isAlias(string $name)
    {
        return isset($this->aliases[$name]);
    }

    /**
     * 从容器中实例给定类型
     *
     * @param string $abstract
     * @param array $parameters
     *
     * @return mixed|object
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public function make(string $abstract, array $parameters = [])
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * 判断指定类型是否为单例
     *
     * @param $abstract
     *
     * @return bool
     */
    public function isSingleton($abstract)
    {
        return isset($this->instances[$abstract]) ||
                $this->bindings[$abstract]['shared'] === self::SHARED_SINGLETON ||
                    $this->bindings[$abstract]['shared'] === self::SHARED_REQUEST;
    }

    /**
     * 判断是否为请求级的单例
     *
     * @param $abstract
     *
     * @return bool
     */
    public function isRequestSingleton($abstract)
    {
        return isset($this->requestInstances[$abstract]) ||
                    $this->bindings[$abstract]['shared'] === self::SHARED_REQUEST;
    }

    /**
     * 判断是否为原型服务
     *
     * @param $abstract
     *
     * @return bool
     */
    public function isPrototype($abstract)
    {
        return isset($this->requestInstances[$abstract]) ||
            $this->bindings[$abstract]['shared'] === self::SHARED_PROTOTYPE;
    }

    /**
     * 判断是否已经解析
     *
     * @param $abstract
     *
     * @return bool
     */
    public function resolved($abstract)
    {
        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->resolved[$abstract]) ||
            isset($this->instances[$abstract]) ||
            isset($this->prototype[$abstract]);
    }

    /**
     * 向容器中注册一个服务
     *
     * @param string $abstract
     * @param null $concrete
     * @param int $shared
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public function bind(string $abstract, $concrete = null, int $shared = self::SHARED_PROTOTYPE)
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);

        if ($concrete == null) {
            $concrete = $abstract;
        }

        if (!($concrete instanceof Closure)) {
            $concrete = function (Container $container, $parameters = []) use ($abstract, $concrete) {
                if ($abstract == $concrete) {
                    return $container->build($concrete);
                }

                return $container->resolve($concrete, $parameters);
            };
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * bindIf
     *
     * @param string $abstract
     * @param null $concrete
     * @param int $shared
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public function bindIf($abstract, $concrete = null, int $shared = self::SHARED_PROTOTYPE)
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    /**
     * call
     *
     * @param callable|string $callback
     * @param array $parameters
     *
     * @return mixed
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public function call($callback, array $parameters = [])
    {
        return BoundMethod::call($this, $callback, $parameters);
    }

    /**
     * 刷新
     */
    public function flush()
    {
        $this->aliases = [];
        $this->bindings = [];
        $this->instances = [];
        $this->abstractAliases = [];
        $this->prototype = [];
    }

    /**
     * 向容器中注册一个服务或绑定一个抽象(单例模式)
     *
     * @param string $abstract
     * @param mixed|null $concrete
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public function singleton(string $abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, self::SHARED_SINGLETON);
    }

    /**
     * 向容器中注册一个服务或绑定一个抽象(请求单例模式)
     *
     * @param string $abstract
     * @param null $concrete
     *
     * @return mixed|void
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public function requestSingleton(string $abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, self::SHARED_REQUEST);
    }

    /**
     * @param array|string $concrete
     *
     * @return ContextualBindingBuilder|ContextualBindingBuilderInterface
     */
    public function when($concrete)
    {
        $aliases = [];

        if ($concrete == null) {
            $concrete = [];
        } elseif (!is_array($concrete)) {
            $concrete = [$concrete];
        }

        foreach ($concrete as $c) {
            $aliases[] = $this->getAlias($c);
        }

        return new ContextualBindingBuilder($this, $aliases);
    }

    /**
     * 扩展容器中的抽象
     *
     * @param $abstract
     * @param Closure $closure
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public function extend($abstract, Closure $closure)
    {
        $abstract = $this->getAlias($abstract);
        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);
        } else {
            $this->extenders[$abstract][] = $closure;
        }
    }

    /**
     * 构建具体实例
     *
     * @param $concrete
     *
     * @return mixed|object
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public function build($concrete)
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $this->getLastParameterOverride());
        }

        $reflector = new ReflectionClass($concrete);

        // 判断是否能够实例化，如不能则抛出异常
        if (!$reflector->isInstantiable()) {
            return $this->notInstantiable($concrete);
        }

        $this->buildStack[] = $concrete;
        $constructor = $reflector->getConstructor();

        // 判断是否存在构造方法，如果不存在则无需解析依赖关系
        if (is_null($constructor)) {
            array_pop($this->buildStack);
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        // 如果存在构造函数，则将依赖逐个解析并实例化
        $instances = $this->resolveDependencies(
            $dependencies
        );

        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * @param string $concrete
     * @param string $abstract
     * @param Closure|string $implementation
     *
     * @return mixed|void
     */
    public function addContextualBinding($concrete, $abstract, $implementation)
    {
        $this->contextual[$concrete][$this->getAlias($abstract)] = $implementation;
    }

    /**
     * 抛出无法实例化错误
     *
     * @param $concrete
     *
     * @throws NotInstantiableException
     */
    protected function notInstantiable($concrete)
    {
        if (!empty($this->buildStack)) {
            $previous = implode(', ', $this->buildStack);
            $message = "Target [$concrete] is not instantiable while building [$previous].";
        } else {
            $message = "Target [$concrete] is not instantiable.";
        }

        throw new NotInstantiableException($message);
    }

    /**
     * 解析指定类型
     *
     * @param string $abstract
     * @param array $parameters
     *
     * @return mixed|object
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    protected function resolve(string $abstract, $parameters = [])
    {
        $abstract = $this->getAlias($abstract);

        $noNeedsContextualBuild = empty($parameters) || $this->getContextualConcrete($abstract) === null;

        if (isset($this->instances[$abstract]) && $noNeedsContextualBuild) {
            return $this->instances[$abstract];
        }

        if (isset($this->requestInstances[$abstract]) && $noNeedsContextualBuild) {
            return $this->requestInstances[$abstract];
        }

        if (isset($this->prototype[$abstract]) && $noNeedsContextualBuild) {
            return clone $this->prototype[$abstract];
        }

        $this->with[] = $parameters;
        $concrete = $this->getConcrete($abstract);

        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete);
        } else {
            $object = $this->make($concrete);
        }

        // 是否定义了扩展器，如果已定义则将扩展应用到正在构建的实例上，通常用于更改对象配置
        foreach ($this->getExtenders($abstract) as $extender) {
            $object = $extender($object, $this);
        }

        if ($this->isSingleton($abstract) && $noNeedsContextualBuild) {
            $this->instances[$abstract] = $object;
        }

        if ($this->isRequestSingleton($abstract) && $noNeedsContextualBuild) {
            $this->requestInstances = $object;
        }

        if ($this->isPrototype($abstract) && $noNeedsContextualBuild) {
            $this->prototype[$abstract] = $object;
        }

        array_pop($this->with);

        return $object;
    }

    /**
     * 获取尾部的参数覆盖值
     *
     * @return array|mixed
     */
    protected function getLastParameterOverride()
    {
        return count($this->with) ? end($this->with) : [];
    }

    /**
     * 通过反射api获取到的一组参数反射api对象来解决实例依赖问题
     *
     * @param array $dependencies
     *
     * @return array
     *
     * @throws BindingResolutionException
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    protected function resolveDependencies(array $dependencies)
    {
        $results = [];
        foreach ($dependencies as $dependency) {
            // 判断依赖参数是否需要传入特定值，如果需要则覆盖
            if ($this->hasParameterOverride($dependency)) {
                $results[] = $this->getParameterOverride($dependency);
                continue;
            }

            // 如果限定类型提示为null则表示参数是一个非对象类型
            $results[] = is_null($dependency->getClass())
                ? $this->resolvePrimitive($dependency)
                : $this->resolveClass($dependency);
        }

        return $results;
    }

    /**
     * 判断依赖是否需要传入特定值的参数
     *
     * @param $dependency
     *
     * @return bool
     */
    protected function hasParameterOverride($dependency)
    {
        return array_key_exists(
            $dependency->name, $this->getLastParameterOverride()
        );
    }

    /**
     * 获取依赖的特定参数值
     *
     * @param \ReflectionParameter $dependency
     *
     * @return mixed
     */
    protected function getParameterOverride($dependency)
    {
        return $this->getLastParameterOverride()[$dependency->name];
    }

    /**
     * 解析原始类型参数
     *
     * @param ReflectionParameter $parameter
     *
     * @return mixed|void
     *
     * @throws BindingResolutionException
     * @throws \ReflectionException
     */
    protected function resolvePrimitive(ReflectionParameter $parameter)
    {
        if (!is_null($concrete = $this->getContextualConcrete('$' . $parameter->name))) {
            return $concrete instanceof Closure ? $concrete($this) : $concrete;
        }

        // 如果存在默认值则返回默认值
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $this->unresolvablePrimitive($parameter);
    }

    /**
     * 抛出一个依赖无法解析异常
     *
     * @param ReflectionParameter $parameter
     *
     * @throws BindingResolutionException
     */
    protected function unresolvablePrimitive(ReflectionParameter $parameter)
    {
        $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";

        throw new BindingResolutionException($message);
    }

    /**
     * 通过容器解析依赖中的类
     *
     * @param ReflectionParameter $parameter
     *
     * @return mixed|object
     *
     * @throws EntryNotFoundException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     * @throws BindingResolutionException
     */
    protected function resolveClass(ReflectionParameter $parameter)
    {
        try {

            return $this->make($parameter->getClass()->name);

        } catch (BindingResolutionException $e) {

            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }

            throw $e;
        }
    }

    /**
     * 获取抽象上下文
     *
     * @param $abstract
     *
     * @return mixed|void
     */
    protected function getContextualConcrete($abstract)
    {
        if (!is_null($binding = $this->findInContextualBindings($abstract))) {
            return $binding;
        }

        if (empty($this->abstractAliases[$abstract])) {
            return;
        }

        foreach ($this->abstractAliases[$abstract] as $alias) {
            if (!is_null($binding = $this->findInContextualBindings($alias))) {
                return $binding;
            }
        }
    }

    /**
     * 获取抽象上下文
     *
     * @param $abstract
     *
     * @return mixed|void
     */
    protected function findInContextualBindings($abstract)
    {
        if (isset($this->contextual[end($this->buildStack)][$abstract])) {
            return $this->contextual[end($this->buildStack)][$abstract];
        }
    }

    /**
     * 获取给定抽象的具体化
     *
     * @param $abstract
     *
     * @return mixed
     */
    protected function getConcrete($abstract)
    {
        if (!is_null($concrete = $this->getContextualConcrete($abstract))) {
            return $concrete;
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * 判断具体是否可构建
     *
     * @param $concrete
     * @param $abstract
     *
     * @return bool
     */
    protected function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * 获取指定类型的扩展器
     *
     * @param $abstract
     *
     * @return array|mixed
     */
    protected function getExtenders($abstract)
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->extenders[$abstract])) {
            return $this->extenders[$abstract];
        }

        return [];
    }

}