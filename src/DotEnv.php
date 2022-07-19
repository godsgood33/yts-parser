<?php

namespace YTS;

/**
 * Class to retrieve and store environment variables
 */
class DotEnv
{
    /**
     * Variable to decide if we are loading $_SERVER superglobal
     *
     * @var bool
     */
    public static bool $LOAD_SERVER = true;

    /**
     * Variable to decide if we are saving variables with `putenv`
     *
     * @var bool
     */
    public static bool $LOAD_ENV = true;

    /**
     * Variable to decide if we are loading $_ENV superglobal
     *
     * @var bool
     */
    public static bool $LOAD_ENV_GLOBAL = true;

    /**
     * Variable to decide if we are defining constants
     *
     * @var bool
     */
    public static bool $DEFINE = true;

    /**
     * Load method
     *
     * @param string $path
     *
     * @throws RuntimeException
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf("%s file is not present", $path));
        }
        
        if (!is_readable($path)) {
            throw new \RuntimeException(sprintf('%s file is not readable', $path));
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && self::$LOAD_SERVER) {
                $_SERVER[$name] = $value;
            }

            if (!array_key_exists($name, $_ENV) && self::$LOAD_ENV_GLOBAL) {
                $_ENV[$name] = $value;
            }

            if (!getenv($name) && self::$LOAD_ENV) {
                putenv(sprintf('%s=%s', $name, $value));
            }
            
            if (!defined($name) && self::$DEFINE) {
                define($name, $value);
            }
        }
    }
}
