<?php

set_time_limit(0);

chdir(getcwd());

require 'init_autoloader.php';

Crontab\Kernel\Phpcron::main(new Crontab\Config\Cli\Option($argv));