# Cache Service Provider

This service provider for Speedwork uses the Cache classes from [Doctrine
Common][] to provide a `cache` service to a Speedwork application, and
other service providers.

[Doctrine Cache]: https://github.com/doctrine/cache

## Install

If you haven't got composer:

    % wget http://getcomposer.org/composer.phar

Add `speedwork/cache` to your `composer.json`:

    % php composer.phar require speedwork/cache:dev-master

## Usage

### Configuration

If you only need one application wide cache, then it's sufficient to
only define a default cache, by setting the `default` key in `cache.options`.

The cache definition is an array of options, with `driver` being the
only mandatory option. All other options in the array, are treated as
constructor arguments to the driver class.

The cache named `default` is the cache available through the app's
`cache` service.

```php
<?php

$app = new Speedwork\Application;

$app->register(new \Speedwork\Cache\CacheServiceProvider, array(
    'cache.options' => array("default" => array(
        "driver" => "apc"
    ))
));
```

The driver name can be either:

* A fully qualified class name
* A simple identifier like "apc", which then gets translated to
  `\Doctrine\Common\Cache\ApcCache`.
* A Closure, which returns an object implementing
  `\Doctrine\Common\Cache\Cache`.

This cache is then available through the `cache` service, and provides
an instance of `Doctrine\Common\Cache\Cache`:

```php
if ($app['cache']->contains('foo')) {
    echo $app['cache']->fetch('foo'), "<br>";
} else {
    $app['cache']->save('foo', 'bar');
}
```

To configure multiple caches, define them as additional keys in
`cache.options`:

```php
$app->register(new \Speedwork\Cache\CacheServiceProvider, array(
    'cache.options' => array(
        'default' => array('driver' => 'apc'),
        'file' => array(
            'driver' => 'filesystem',
            'directory' => '/tmp/myapp'
        ),
        'global' => array(
            'driver' => function() {
                $redis = new \Doctrine\Common\Cache\RedisCache;
                $redis->setRedis($app['redis']);

                return $redis;
            }
        )
    )
));
```

All caches (including the default) are then available via the `caches`
service:

```php
$app['caches.file']->save('foo', 'bar');
```