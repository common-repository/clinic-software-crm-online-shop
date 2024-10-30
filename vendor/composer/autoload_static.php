<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc09ebe90789d0f8bd90c7ffc5e96dc4d
{
    public static $prefixLengthsPsr4 = array (
        'C' => 
        array (
            'ClinicSoftware\\' => 15,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ClinicSoftware\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc09ebe90789d0f8bd90c7ffc5e96dc4d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc09ebe90789d0f8bd90c7ffc5e96dc4d::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitc09ebe90789d0f8bd90c7ffc5e96dc4d::$classMap;

        }, null, ClassLoader::class);
    }
}
