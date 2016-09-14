<?php

set_time_limit(0);

require 'init_autoloader.php';

Crontab\Kernel\Phpcron::main($argv);