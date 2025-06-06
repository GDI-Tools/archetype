<?php

namespace Archetype\Vendor;

require 'SplClassLoader.php';
$loader = new SplClassLoader('Analog', '../lib');
$loader->register();
use Archetype\Vendor\Analog\Analog;
$log = '';
Analog::handler(\Archetype\Vendor\Analog\Handler\Variable::init($log));
Analog::log('Test one');
Analog::log('Test two');
echo $log;
