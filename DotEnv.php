<?php

namespace Godsgood33;

/**
 * Class to retrieve and store environment variables
 *
 * @author Ryan Prather <godsgood33@gmail.com>
 */
class DotEnv
{
    /**
     * Load method
     *
     * @param string $path
     *
     * @throws RuntimeException
     */
    public static function load(string $path) :void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf("%s file is not present", $path));
        } elseif (!is_readable($path)) {
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

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
            
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
}
