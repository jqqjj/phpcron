<?php


namespace Crontab\Helper;

use Crontab\Logger\Container\Logger AS LoggerContainer;


class TaskRule
{
    private $_rule;
    private $_range=array();
    
    public function __construct($rule)
    {
        $this->_rule = $rule;
        $this->_range = array(
            'second'=>array(),
            'minute'=>array(),
            'hour'=>array(),
            'day'=>array(),
            'month'=>array(),
            'week'=>array(),
        );
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
            if(!preg_match('/^(((\d{1,2}\-\d{1,2})(\/\d{1,2})?)|((\*(\/\d{1,2})?)|\d{1,2}))(\,(((\d{1,2}\-\d{1,2})(\/\d{1,2})?)|((\*(\/\d{1,2})?)|\d{1,2})))*$/', $value))
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
        
        LoggerContainer::getDefaultDriver()->log(print_r($this->_range,true));
        
        return TRUE;
    }
    
    private function _verifySecond($rule)
    {
        $points = $this->_getRulePoints($rule, 0, 59);
        if($points===FALSE)
        {
            return FALSE;
        }
        
        $this->_range['second'] = $points;
        
        return TRUE;
    }
    private function _verifyMinute($rule)
    {
        $points = $this->_getRulePoints($rule, 0, 59);
        if($points===FALSE)
        {
            return FALSE;
        }
        
        $this->_range['minute'] = $points;
        
        return TRUE;
    }
    private function _verifyHour($rule)
    {
        $points = $this->_getRulePoints($rule, 0, 23);
        if($points===FALSE)
        {
            return FALSE;
        }
        
        $this->_range['hour'] = $points;
        
        return TRUE;
    }
    private function _verifyDay($rule)
    {
        $points = $this->_getRulePoints($rule, 1, 31);
        if($points===FALSE)
        {
            return FALSE;
        }
        
        $this->_range['day'] = $points;
        
        return TRUE;
    }
    private function _verifyMonth($rule)
    {
        $points = $this->_getRulePoints($rule, 1, 12);
        if($points===FALSE)
        {
            return FALSE;
        }
        
        $this->_range['month'] = $points;
        
        return TRUE;
    }
    private function _verifyWeek($rule)
    {
        $points = $this->_getRulePoints($rule, 0, 7);
        if($points===FALSE)
        {
            return FALSE;
        }
        
        $this->_range['week'] = $points;
        
        return TRUE;
    }
    
    /**
     * 
     * @param type $rule
     * @param type $min
     * @param type $max
     */
    private function _getRulePoints($rule, $min, $max)
    {
        $points = range($min, $max);
        $cal_points = array();
        
        #item: x-y/z
        foreach (explode(',', $rule) AS $item)
        {
            $rs = $this->_parseRuleItem($item, $min, $max);
            if(!is_array($rs))
            {
                return FALSE;
            }
            $cal_points = array_merge($cal_points,$rs);
        }
        
        if(array_diff($cal_points, $points))
        {
            return FALSE;
        }
        else
        {
            return $cal_points;
        }
    }
    
    private function _parseRuleItem($item,$min,$max)
    {
        $spit = explode('/', $item);
        $limit = explode('-', $spit[0]);
        
        if(isset($spit[1]) && (intval($spit[1])<=0 || intval($spit[1])>$max))
        {
            return FALSE;
        }
        if($limit[0]=='*')
        {
            if(isset($limit[1]))
            {
                return FALSE;
            }
        }
        elseif( intval($limit[0])>$max || ( isset($limit[1]) && intval($limit[1])<=intval($limit[0]) ) || ( isset($limit[1]) && intval($limit[1])>$max ))
        {
            return FALSE;
        }
        
        $interval = isset($spit[1]) ? intval($spit[1]) : 1;
        $range_min = $limit[0]=='*' ? $min : intval($limit[0]);
        $range_max = $limit[0]=='*' ? $max : (isset($limit[1]) ? intval($limit[1]) : intval($limit[0]));
        
        $walker = $range_min;
        $range = array();
        do{
            array_push($range, $walker);
            $walker += $interval;
        }while($walker<=$range_max);
        
        return $range;
    }
}