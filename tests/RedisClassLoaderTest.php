<?php
/**
 * Domain Tests
 *
 * @copyright 2014, Михаил Красильников <m.krasilnikov@yandex.ru>
 * @author Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Symfony\ClassLoader\Tests;

use Composer\Autoload\ClassLoader;
use Mekras\Symfony\ClassLoader\RedisClassLoader;

/**
 * RedisClassLoader Tests
 *
 * @covers Mekras\Symfony\ClassLoader\RedisClassLoader
 */
class RedisClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     *
     */
    public function testFindFile()
    {
        $loader = new ClassLoader();
        $loader->add('Foo', __DIR__ . '/fixtures');

        $redis = $this->getMockBuilder('Redis')->disableOriginalConstructor()
            ->setMethods(array('get', 'set'))->getMock();
        $redis->expects($this->exactly(2))->method('get')->with('abcFoo\Bar')->willReturnCallback(
            function () {
                static $it = 1;
                return $it++ == 1 ? false : 'filename';
            }
        );
        $redis->expects($this->once())->method('set')->with('abcFoo\Bar');

        $cachedLoader = new RedisClassLoader($redis, 'abc', $loader);

        $this->assertNotFalse($cachedLoader->findFile('Foo\Bar'));
        $this->assertNotFalse($cachedLoader->findFile('Foo\Bar'));
    }
}
