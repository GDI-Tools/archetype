<?php

namespace Archetype\Vendor\Analog\Handler;

/**
 * Translates log level codes to their names
 *
 *
 * Usage:
 *
 *     // The log level (3rd value) must be formatted as a string
 *     Analog::$format = "%s - %s - %s - %s\n";
 * 
 *     Analog::handler (Analog\Handler\LevelName::init (
 *         Analog\Handler\File::init ($file)
 *     ));
 */
class LevelName
{
    /**
     * Translation list for log levels.
     */
    private static $log_levels = array(\Archetype\Vendor\Analog\Analog::DEBUG => 'DEBUG', \Archetype\Vendor\Analog\Analog::INFO => 'INFO', \Archetype\Vendor\Analog\Analog::NOTICE => 'NOTICE', \Archetype\Vendor\Analog\Analog::WARNING => 'WARNING', \Archetype\Vendor\Analog\Analog::ERROR => 'ERROR', \Archetype\Vendor\Analog\Analog::CRITICAL => 'CRITICAL', \Archetype\Vendor\Analog\Analog::ALERT => 'ALERT', \Archetype\Vendor\Analog\Analog::URGENT => 'URGENT');
    public static function init($handler)
    {
        return new LevelName($handler);
    }
    /**
     * For use as a class instance
     */
    private $_handler;
    public function __construct($handler)
    {
        $this->_handler = $handler;
    }
    public function log($info)
    {
        if (isset(self::$log_levels[$info['level']])) {
            $info['level'] = self::$log_levels[$info['level']];
        }
        call_user_func($this->_handler, $info);
    }
}
