<?php

/* 
 * Copyright (C) 2016 phpcron
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace TaskPlugin;

use Crontab\Task\TaskInterface;

class Curl implements TaskInterface
{
    private $_params;
    
    public function getConfig()
    {
        return "*/5 * * * * *";
    }
    
    public function canWork()
    {
        return TRUE;
    }
    
    public function onStart(array $data)
    {
        $this->_params = $data;
    }
    
    public function onStop()
    {
        
    }
    
    public function onWork()
    {
        $this->_curl($this->_params['url'], $this->_params['method'], array('t'=>time()));
    }
    
    private function _curl($url,$method,$data)
    {
        $curl = curl_init();
        
        switch (strtoupper($method))
        {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                $real_url = $url;
                break;
            case "GET":
                $real_url = $url . (strpos($url, "?") ? '&' : '?') . http_build_query($data);
            default :
                
        }
        
        curl_setopt($curl, CURLOPT_URL, $real_url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.111 Safari/537.36");
        $response = curl_exec($curl);
        
        curl_close($curl);
        
        return $response;
    }
}