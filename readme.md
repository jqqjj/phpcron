#**PHP crontab**

A framework like Linux timing tasks(crontab).

Supports second level and be compatible with expression of linux crontab.

Require:

* `php >= 5.5` 
* `pcntl supports` 


Use
-----

> php Server.php

Add a task plugin
------------------------
#####**1.Append an item config in config file (config/local/config.php) :**

```php
<?php
return array(
	'plugins'=>array(
		#item config start
		'myTaskPlugin'=>array(
			'class'=>'TaskPlugin\myTaskPluginClass',
			'enabled'=>TRUE,
			'params'=>array(
				'myTaskPluginParam1'=>'VALUE',
				'myTaskPluginParam2'=>'VALUE',
			),
		),
		#end of item config
	),
);
```

#####**2.Add Plugin Class in Path(TaskPlugin/):**
```php
<?php
namespace TaskPlugin;
use Crontab\Task\TaskInterface;
class myTaskPluginClass implements TaskInterface
{
	private $_params;
	public function getConfig()
	{
		return "*/5 * * * * *";
	}
	public function canWork()
	{
		echo "Allow to do task.";
		return TRUE;
	}
	public function onStart(array $data)
	{
		echo "I'm Starting task.";
		$this->_params = $data;
	}
	public function onStop()
	{
		echo "I'm stopping task.";
	}
	public function onWork()
	{
		echo "I'm doing task.";
	}
}
```

Crontab syntax
---------------------

A CRON expression is a string representing the schedule for a particular command to execute.  The parts of a CRON schedule are as follows:

    *    *    *    *    *    *
    -    -    -    -    -    -
    |    |    |    |    |    |
    |    |    |    |    |    + day of week (0 - 7) (Sunday=0 or 7)
    |    |    |    |    +----- month(1 - 12)
    |    |    |    +---------- day(1 - 31)
    |    |    +--------------- hour (0 - 23)
    |    +-------------------- minute(0 - 59)
    +------------------------- second(0 - 59)[optional]

Each of the parts supports wildcards (\*), wildcards (\*/2),ranges (2-5) ,ranges (2-5/2),and lists (2,5,6-8,9-12/3).