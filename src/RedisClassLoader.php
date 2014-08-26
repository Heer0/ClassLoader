<?php
/**
 * Implements a wrapping autoloader cached in Redis.
 */
namespace Mekras\Symfony\ClassLoader;

use Redis;

/**
 * Implements a wrapping autoloader cached in Redis.
 *
 * It expects an object implementing a findFile method to find the file. This
 * allow using it as a wrapper around the other loaders of the component (the
 * ClassLoader and the UniversalClassLoader for instance) but also around any
 * other autoloader following this convention (the Composer one for instance)
 *
 * ```php
 * $loader = new ClassLoader();
 *
 * // register classes with namespaces
 * $loader->add('Symfony\Component', __DIR__ . '/component');
 * $loader->add('Symfony', __DIR__ . '/framework');
 *
 * $cachedLoader = new RedisClassLoader('my_prefix', $loader);
 *
 * // activate the cached autoloader
 * $cachedLoader->register();
 *
 * // eventually deactivate the non-cached loader if it was registered previously
 * // to be sure to use the cached one.
 * $loader->unregister();
 * ```
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Kris Wallsmith <kris@symfony.com>
 * @author Михаил Красильников <m.krasilnikov@yandex.ru>
 *
 * @api
 * @since  1.00
 */
class RedisClassLoader
{
    /**
     * The class loader object being decorated
     *
     * @var object a class loader object that implements the findFile() method
     * @since 1.00
     */
    protected $decorated;

    /**
     * Custom uid to distinguish applications
     *
     * @var string
     * @since 1.01
     */
    private $uid;

    /**
     * Interface to redis
     *
     * @var Redis
     * @since 1.00
     */
    private $redis;

    /**
     * Constructs new wrapper
     *
     * @param Redis  $redis     interface to Redis
     * @param string $uid       uid to distinguish applications
     * @param object $decorated a class loader object that implements the findFile() method
     *
     * @throws \InvalidArgumentException
     *
     * @api
     * @since 1.00
     */
    public function __construct(Redis $redis, $uid, $decorated)
    {
        $this->redis = $redis;

        if (!method_exists($decorated, 'findFile')) {
            throw new \InvalidArgumentException(
                'The class finder must implement a "findFile" method.'
            );
        }

        $this->uid = 'symfony.autoload.' . $uid;
        $this->decorated = $decorated;
    }

    /**
     * Passes through all unknown calls onto the decorated object
     *
     * @since 1.00
     */
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->decorated, $method), $args);
    }

    /**
     * Registers this instance as an autoloader
     *
     * @param bool $prepend whether to prepend the autoloader or not
     *
     * @since 1.00
     */
    public function register($prepend = false)
    {
        spl_autoload_register(array($this, 'loadClass'), true, $prepend);
    }

    /**
     * Unregisters this instance as an autoloader
     *
     * @since 1.00
     */
    public function unregister()
    {
        spl_autoload_unregister(array($this, 'loadClass'));
    }

    /**
     * Loads the given class or interface.
     *
     * @param string $class the name of the class
     *
     * @return bool|null true, if loaded
     *
     * @since 1.00
     */
    public function loadClass($class)
    {
        if ($file = $this->findFile($class)) {
            /** @noinspection PhpIncludeInspection */
            require $file;
            return true;
        }
        return null;
    }

    /**
     * Finds a file by class name while caching lookups to Redis
     *
     * @param string $class a class name to resolve to file
     *
     * @return string|null
     *
     * @since 1.00
     */
    public function findFile($class)
    {
        $file = $this->redis->hGet($this->uid, $class);
        if (false === $file) {
            $file = $this->decorated->findFile($class);
            $this->redis->hSet($this->uid, $class, $file);
        }

        return $file;
    }
}
