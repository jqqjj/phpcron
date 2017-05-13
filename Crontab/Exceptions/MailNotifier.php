<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Crontab\Exceptions;

use Crontab\Mail\PHPMailer;
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
        
        $sender->clearAddresses();
        $sender->clearAllRecipients();
        $sender->clearAttachments();
        $sender->clearBCCs();
        $sender->clearCCs();
        $sender->clearCustomHeaders();
        $sender->clearReplyTos();
        
        $sender->setFrom($this->_mail_config['from_user'], $this->_mail_config['from_user']);
        $sender->addAddress($this->_mail_config['to_user'], $this->_mail_config['to_user']);
        $sender->addReplyTo($this->_mail_config['from_user'], $this->_mail_config['from_user']);
        $sender->isHTML(true);
        
        $sender->Subject = 'You got an error from phpcron.';
        $sender->msgHTML($message);
        return $sender->send();
    }
    
    private function _getSender()
    {
        if(empty($this->_sender) || !$this->_sender instanceof PHPMailer)
        {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->_mail_config['smtp']['host'].":".$this->_mail_config['smtp']['port'];
            if(isset($this->_mail_config['smtp']['user']) && isset($this->_mail_config['smtp']['password'])){
                $mail->SMTPAuth = true;
                $mail->Username = $this->_mail_config['smtp']['user'];
                $mail->Password = $this->_mail_config['smtp']['password'];
            }
            $mail->CharSet = 'UTF-8';
            
            $this->_sender = $mail;
            return $this->_sender;
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