<?php


namespace Crontab\Helper;

use Crontab\Logger\Container\Logger AS LoggerContainer;


class TaskRule
{
    private $_rule;
    private $_range=array();
    private $_deep_search_years = 5;
    
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
        $this->_calNextTimeList($time, 15);
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
        
        return TRUE;
    }
    
    private function _calNextTimeList($now,$num)
    {
        $now_day_time = strtotime(date("Y-m-d", $now));
        $cal_days = array();
        
        for($i=0,$len=floor((strtotime("+{$this->_deep_search_years} years",$now)-$now)/86400);$i<$len;$i++)
        {
            if(count($cal_days)>=$num)
            {
                break;
            }
            $time = strtotime("+{$i} days",$now_day_time);
            if(in_array(date("j",$time),  $this->_range['day']) && in_array(date("w",$time),  $this->_range['week']) && in_array(date("n",$time),  $this->_range['month']))
            {
                $cal_days[] = date("Y-m-d",$time);
            }
        }
        
        foreach ($cal_days AS $value)
        {
            LoggerContainer::getDefaultDriver()->log("_calNextTimeList start:".$value." ".date("H:i:s",$now));
            $start_time = strtotime($value." ".date("H:i:s",$now));
            $end_time = strtotime("+1 days",strtotime($value))-1;
            LoggerContainer::getDefaultDriver()->log("starttime:".$start_time);
            LoggerContainer::getDefaultDriver()->log("endtime:".$end_time);
        }
        
        //LoggerContainer::getDefaultDriver()->log(print_r($cal_days,true));
        return $cal_days;
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
        
        if(in_array(7, $points))
        {
            unset($points[array_search(7, $points)]);
            if(!in_array(0, $points))
            {
                array_unshift($points, 0);
            }
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
        
        sort($cal_points);
        
        if(array_diff($cal_points, $points))
        {
            return FALSE;
        }
        else
        {
            return array_values(array_unique($cal_points));
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