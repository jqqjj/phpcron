<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Crontab\Exceptions;

use Crontab\Mail\Mailer;
use Crontab\Config\ConfigManager;

class MailNotifier
{
    private $_mail_config;
    private $_sender;
    private $_last_send_time=0;
    
    public function send($message)
    {
        if(!$this->_getMailConfig())
        {
            return FALSE;
        }
        
        $sender = $this->_getSender();
        if(!empty($this->_last_send_time) && $this->_last_send_time+600>time())
        {
            return FALSE;
        }
        $this->_last_send_time = time();
        return $sender->sendmail($this->_mail_config['to_user'], $this->_mail_config['to_user'], $this->_mail_config['from_user'], $this->_mail_config['from_user'], "You got an error from phpcron", $message);
    }
    
    private function _getSender()
    {
        if(empty($this->_sender) || !$this->_sender instanceof Mailer)
        {
            $this->_sender = new Mailer($this->_mail_config['smtp']['host'], $this->_mail_config['smtp']['port']);
            if(isset($this->_mail_config['smtp']['user']) && isset($this->_mail_config['smtp']['password']))
            {
                $this->_sender->setAuthInfo($this->_mail_config['smtp']['user'], $this->_mail_config['smtp']['password']);
            }
        }
        
        return $this->_sender;
    }
    
    private function _getMailConfig()
    {
        if(empty($this->_mail_config))
        {
            $config = ConfigManager::get('admin_mail');
            if(empty($config) || empty($config['smtp']) || empty($config['smtp']['host']) || empty($config['smtp']['port'])
                    || empty($config['from_user']) || empty($config['to_user']))
            {
                return FALSE;
            }
            $this->_mail_config = $config;
        }
        
        return TRUE;
    }
}