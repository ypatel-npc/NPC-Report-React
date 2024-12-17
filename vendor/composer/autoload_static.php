<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit5e3024da55ac8b2d511ae59780ee3c85
{
    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'MyWPReact\\Core\\' => 15,
            'MyWPReact\\Admin\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'MyWPReact\\Core\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
        'MyWPReact\\Admin\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'MyWPReact\\Admin\\Admin' => __DIR__ . '/../..' . '/includes/Admin.php',
        'MyWPReact\\Core\\Loader' => __DIR__ . '/../..' . '/includes/Loader.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit5e3024da55ac8b2d511ae59780ee3c85::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit5e3024da55ac8b2d511ae59780ee3c85::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit5e3024da55ac8b2d511ae59780ee3c85::$classMap;

        }, null, ClassLoader::class);
    }
}
