<?php

namespace Crontab\Config\Cli;

class Option
{
    protected $_argvs;
    
    protected $_filter = array('start','stop','reload');

    public function __construct($argvs)
    {
        $this->_argvs = $this->_parseArgvs($argvs);
    }

    public function getOption()
    {
        return $this->_argvs;
    }
    
    public function setOption($argvs)
    {
        $this->_parseArgvs($argvs);
    }
    
    protected function _parseArgvs($argvs)
    {
        if(is_array($argvs))
        {
            $argvs_arr = $argvs;
        }
        else
        {
            $argvs_arr = preg_split('/\s+/', $argvs);
        }
        
        return array_intersect($argvs_arr, $this->_filter);
    }
}