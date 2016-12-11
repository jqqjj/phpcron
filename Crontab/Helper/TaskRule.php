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
    
    public function getNextWorkTime()
    {
        return time()+10;
    }
    
    public function verify()
    {
        $rules = preg_split('/\s+/', $this->_rule);
        if(!in_array(count($rules),array(5,6)))
        {
            return FALSE;
        }
        
        foreach ($rules AS $value)
        {
            if(!preg_match('/^(((\d{1,2}\-\d{1,2})(\/\d{1,2})?)|((\*(\/\d{1,2})?)|\d{1,2}))(\,(((\d{1,2}\-\d{1,2})(\/\d{1,2})?)|((\*(\/\d{1,2})?)|\d{1,2})))*/', $value))
            {
                return FALSE;
            }
        }
        
        switch (count($rules))
        {
            case 5:
                if(!$this->_verifyMinute($rules[0]) || !$this->_verifyHour($rules[1]) || !$this->_verifyDay($rules[2])
                        || !$this->_verifyMonth($rules[3]) || !$this->_verifyWeek($rules[4]))
                {
                    return FALSE;
                }
                break;
            case 6:
                if(!$this->_verifySecond($rules[0]) || !$this->_verifyMinute($rules[1]) || !$this->_verifyHour($rules[2])
                        || !$this->_verifyDay($rules[3]) || !$this->_verifyMonth($rules[4]) || !$this->_verifyWeek($rules[5]))
                {
                    return FALSE;
                }
                break;
            default :
                return FALSE;
        }
        
        
        
        return TRUE;
    }
    
    private function _verifySecond($rule)
    {
        $pieces = preg_split('/\,/', '20-30/2,*/3,30-40/5');
        #$pieces = preg_split('/\,/', $rule);
        if(empty($pieces))
        {
            return FALSE;
        }
        foreach ($pieces AS $value)
        {
            
        }
        
        LoggerContainer::getDefaultDriver()->log(print_r($pieces,true));
        return TRUE;
    }
    private function _verifyMinute($rule)
    {
        return TRUE;
    }
    private function _verifyHour($rule)
    {
        return TRUE;
    }
    private function _verifyDay($rule)
    {
        return TRUE;
    }
    private function _verifyMonth($rule)
    {
        return TRUE;
    }
    private function _verifyWeek($rule)
    {
        return TRUE;
    }
}