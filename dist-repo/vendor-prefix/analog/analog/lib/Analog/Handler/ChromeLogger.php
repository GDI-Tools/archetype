<?php

namespace Archetype\Vendor\Analog\Handler;

require_once __DIR__ . '/../../ChromePhp.php';
/**
 * Log to the [Chrome Logger](http://craig.is/writing/chrome-logger).
 * Based on the [ChromePhp library](https://github.com/ccampbell/chromephp).
 *
 * Usage:
 *
 *     Analog::handler (Analog\Handler\ChromeLogger::init ());
 *     
 *     // send a debug message
 *     Analog::debug ($an_object);
 *
 *     // send an ordinary message
 *     Analog::info ('An error message');
 */
class ChromeLogger
{
    public static function init()
    {
        return function ($info) {
            switch ($info['level']) {
                case \Archetype\Vendor\Analog\Analog::DEBUG:
                    \Archetype\Vendor\ChromePhp::log($info['message']);
                    break;
                case \Archetype\Vendor\Analog\Analog::INFO:
                case \Archetype\Vendor\Analog\Analog::NOTICE:
                    \Archetype\Vendor\ChromePhp::info($info['message']);
                    break;
                case \Archetype\Vendor\Analog\Analog::WARNING:
                    \Archetype\Vendor\ChromePhp::warn($info['message']);
                    break;
                case \Archetype\Vendor\Analog\Analog::ERROR:
                case \Archetype\Vendor\Analog\Analog::CRITICAL:
                case \Archetype\Vendor\Analog\Analog::ALERT:
                case \Archetype\Vendor\Analog\Analog::URGENT:
                    \Archetype\Vendor\ChromePhp::error($info['message']);
                    break;
            }
        };
    }
}
