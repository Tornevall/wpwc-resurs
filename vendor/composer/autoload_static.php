<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit484bfa22ac5522498cfba088583f6124
{
    public static $files = array (
        'bc521b269795605ef2585a7369f0017e' => __DIR__ . '/..' . '/tornevall/tornelib-php-network/src/Network.php',
        'e6cb3e061b463a34be4630aa7d1ecca2' => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/rbapiloader.php',
    );

    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'TorneLIB\\' => 9,
        ),
        'R' => 
        array (
            'Resursbank\\Woocommerce\\' => 23,
            'Resursbank\\RBEcomPHP\\' => 21,
            'Resursbank\\Ecommerce\\' => 21,
            'Resursbank\\Ecom\\' => 16,
            'ResursBank\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'TorneLIB\\' => 
        array (
            0 => __DIR__ . '/..' . '/tornevall/tornelib-php-errorhandler/src',
            1 => __DIR__ . '/..' . '/tornevall/tornelib-php-version/src',
            2 => __DIR__ . '/..' . '/tornevall/tornelib-php-flags/src',
            3 => __DIR__ . '/..' . '/tornevall/tornelib-php-utils/src',
            4 => __DIR__ . '/..' . '/tornevall/tornelib-php-io/src',
            5 => __DIR__ . '/..' . '/tornevall/tornelib-php-crypto/src',
            6 => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/src',
            7 => __DIR__ . '/..' . '/tornevall/tornelib-php-network/src',
        ),
        'Resursbank\\Woocommerce\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Resursbank\\RBEcomPHP\\' => 
        array (
            0 => __DIR__ . '/..' . '/resursbank/ecomphp-deprecated/src',
        ),
        'Resursbank\\Ecommerce\\' => 
        array (
            0 => __DIR__ . '/..' . '/resursbank/ecomphp/src',
        ),
        'Resursbank\\Ecom\\' => 
        array (
            0 => __DIR__ . '/..' . '/resursbank/ecom/src',
        ),
        'ResursBank\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $fallbackDirsPsr0 = array (
        0 => __DIR__ . '/..' . '/resursbank/ecomphp-deprecated/src',
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit484bfa22ac5522498cfba088583f6124::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit484bfa22ac5522498cfba088583f6124::$prefixDirsPsr4;
            $loader->fallbackDirsPsr0 = ComposerStaticInit484bfa22ac5522498cfba088583f6124::$fallbackDirsPsr0;
            $loader->classMap = ComposerStaticInit484bfa22ac5522498cfba088583f6124::$classMap;

        }, null, ClassLoader::class);
    }
}
