<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit9d32d45b4a192e4879ae98ce2ce57339
{
    public static $prefixLengthsPsr4 = array (
        'C' => 
        array (
            'Composer\\Installers\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Composer\\Installers\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/installers/src/Composer/Installers',
        ),
    );

    public static $prefixesPsr0 = array (
        'R' => 
        array (
            'Requests' => 
            array (
                0 => __DIR__ . '/..' . '/rmccue/requests/library',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit9d32d45b4a192e4879ae98ce2ce57339::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit9d32d45b4a192e4879ae98ce2ce57339::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit9d32d45b4a192e4879ae98ce2ce57339::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
