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
    
    public function getNextWorkTime($time)
    {
        return $time+10;
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
        $points = $this->_getRulePoints($rule, 0, 59);
        
        LoggerContainer::getDefaultDriver()->log(print_r($points,true));
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
    
    private function _getRulePoints($rule, $min, $max)
    {
        $points = array_fill(0, 60, FALSE);return $points;
        $items = preg_split('/\,/', '20-30/2,*/3,30-40/5');
        #$items = preg_split('/\,/', $rule);
        if(empty($items))
        {
            return FALSE;
        }
        foreach ($items AS $item)
        {
            $pieces = preg_split('/^\/$/', $item);
        }
    }
}