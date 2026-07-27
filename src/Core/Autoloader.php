<?php

namespace ConsultationBookingPro\Core;

/**
 * PSR-4 Compliant Autoloader for the plugin.
 */
class Autoloader {
    /**
     * @var array Namespace prefixes to base directories.
     */
    protected static $prefixes = [];

    /**
     * Register a namespace and its base directory.
     *
     * @param string $prefix The namespace prefix.
     * @param string $base_dir The base directory for that prefix.
     */
    public static function register(string $prefix, string $base_dir) {
        $prefix = trim($prefix, '\\') . '\\';
        $base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        self::$prefixes[$prefix] = $base_dir;

        spl_autoload_register([self::class, 'load']);
    }

    /**
     * Load the requested class file.
     *
     * @param string $class The fully-qualified class name.
     */
    public static function load(string $class) {
        foreach (self::$prefixes as $prefix => $base_dir) {
            if (strpos($class, $prefix) === 0) {
                $relative_class = substr($class, strlen($prefix));
                $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

                if (file_exists($file)) {
                    require $file;
                    return true;
                }
            }
        }
        return false;
    }
}
