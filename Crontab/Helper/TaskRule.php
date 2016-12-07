<?php


namespace Crontab\Helper;

use Crontab\Logger\Container\Logger AS LoggerContainer;


class TaskRule
{
    private $_rule;
    
    public function __construct($rule)
    {
        $this->_rule = $rule;
    }
    
    public function verify()
    {
        $rules = $this->_splitRule();
        
        LoggerContainer::getDefaultDriver()->log('rules:'.print_r($rules,TRUE));
        
        return TRUE;
    }
    
    private function _splitRule()
    {
        return preg_split('/\s/', $this->_rule);
        $str = '(\*|(\d{1,2}(\,\d{1,2})*(\-\d{1,2})?(\,\d{1,2})*))(\/\d{1,2})?';
    }
}