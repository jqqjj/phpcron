<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Crontab\Mail;

class Mailer
{
    /* Public Variables */
    public $time_out = 30;
    public $auth = false;
    public $smtp_port;
    public $host_name = 'localhost';//is used in HELO command
    public $relay_host;
    public $user;
    public $pass;

    /* Private Variables */
    private $sock;

    /* Constractor */
    public function __construct($relay_host, $smtp_port=25)
    {
        $this->relay_host = $relay_host;
        $this->smtp_port = $smtp_port;

        $this->sock = FALSE;
    }
    
    public function setAuthInfo($user, $pass)
    {
        $this->user = $user;
        $this->pass = $pass;
        $this->auth = TRUE;
    }

    /* Main Function */
    public function sendmail($to,$to_alias,$from,$from_alias, $subject = "", $body = "", $mailtype='HTML', $cc = "", $bcc = "", $additional_headers = "")
    {
        $mail_from = $this->get_address($this->strip_comment($from));
        $header = "MIME-Version:1.0\r\n";
        if ($mailtype == "HTML") {
            $header .= "Content-Type:text/html\r\n";
        }
        $header .= "To: $to_alias<" . $to . ">\r\n";
        if ($cc != "") {
            $header .= "Cc: " . $cc . "\r\n";
        }
        $header .= "From: $from_alias<" . $from . ">\r\n";
        $header .= "Subject: " . $subject . "\r\n";
        $header .= $additional_headers;
        $header .= "Date: " . date("r") . "\r\n";
        $header .= "X-Mailer:By Redhat (PHP/" . phpversion() . ")\r\n";
        list($msec, $sec) = explode(" ", microtime());
        $header .= "Message-ID: <" . date("YmdHis", $sec) . "." . ($msec * 1000000) . "." . $mail_from . ">\r\n";
        $TO = explode(",", $this->strip_comment($to));

        if ($cc != "") {
            $TO = array_merge($TO, explode(",", $this->strip_comment($cc)));
        }

        if ($bcc != "") {
            $TO = array_merge($TO, explode(",", $this->strip_comment($bcc)));
        }

        $sent = TRUE;
        foreach ($TO as $rcpt_to) {
            $rcpt_to = $this->get_address($rcpt_to);
            if (!$this->smtp_sockopen($rcpt_to)) {
                $this->log_write("Error: Cannot send email to " . $rcpt_to . "\n");
                $sent = FALSE;
                continue;
            }
            if (!$this->smtp_send($this->host_name, $mail_from, $rcpt_to, $header, $body))
            {
                $this->log_write("Error: Cannot send email to <" . $rcpt_to . ">\n");
                $sent = FALSE;
            }
            fclose($this->sock);
        }
        
        return $sent;
    }
    
    /* Private Functions */
    private function smtp_send($helo, $from, $to, $header, $body = "")
    {
        if (!$this->smtp_putcmd("HELO", $helo)) {
            return $this->smtp_error("sending HELO command");
        }
        #auth 
        if ($this->auth) {
            if (!$this->smtp_putcmd("AUTH LOGIN", base64_encode($this->user))) {
                return $this->smtp_error("sending HELO command");
            }
            if (!$this->smtp_putcmd("", base64_encode($this->pass))) {
                return $this->smtp_error("sending HELO command");
            }
        }
        # 
        if (!$this->smtp_putcmd("MAIL", "FROM:<" . $from . ">")) {
            return $this->smtp_error("sending MAIL FROM command");
        }
        
        if (!$this->smtp_putcmd("RCPT", "TO:<" . $to . ">")) {
            return $this->smtp_error("sending RCPT TO command");
        }
        
        if (!$this->smtp_putcmd("DATA")) {
            return $this->smtp_error("sending DATA command");
        }
        
        if (!$this->smtp_message($header, $body)) {
            return $this->smtp_error("sending message");
        }
        
        if (!$this->smtp_eom()) {
            return $this->smtp_error("sending <CR><LF>.<CR><LF> [EOM]");
        }
        
        if (!$this->smtp_putcmd("QUIT")) {
            return $this->smtp_error("sending QUIT command");
        }
        
        return TRUE;
    }
    
    private function smtp_sockopen($address)
    {
        if ($this->relay_host == "") {
            return $this->smtp_sockopen_mx($address);
        } else {
            return $this->smtp_sockopen_relay();
        }
    }
    
    private function smtp_sockopen_relay()
    {
        $errno =null;$errstr=null;
        $this->sock = fsockopen($this->relay_host, $this->smtp_port, $errno, $errstr, $this->time_out);
        if (!($this->sock && $this->smtp_ok())) {
            $this->log_write("Error: Cannot connenct to relay host " . $this->relay_host . "\n");
            $this->log_write("Error: " . $errstr . " (" . $errno . ")\n");
            return FALSE;
        }
        return TRUE;
    }
    
    private function smtp_sockopen_mx($address)
    {
        $domain = preg_replace("/^.+@([^@]+)$/", "\\1", $address);
        $MXHOSTS = NULL;
        if (!getmxrr($domain, $MXHOSTS)) {
            $this->log_write("Error: Cannot resolve MX \"" . $domain . "\"\n");
            return FALSE;
        }
        foreach ($MXHOSTS as $host) {
            $errno = null;$errstr=null;
            $this->sock = fsockopen($host, $this->smtp_port, $errno, $errstr, $this->time_out);
            if (!($this->sock && $this->smtp_ok())) {
                $this->log_write("Warning: Cannot connect to mx host " . $host . "\n");
                $this->log_write("Error: " . $errstr . " (" . $errno . ")\n");
                continue;
            }
            return TRUE;
        }
        return FALSE;
    }
    
    private function smtp_message($header, $body)
    {
        fputs($this->sock, $header . "\r\n" . $body);
        return TRUE;
    }
    
    private function smtp_eom()
    {
        fputs($this->sock, "\r\n.\r\n");
        
        return $this->smtp_ok();
    }

    private function smtp_ok()
    {
        $response = str_replace("\r\n", "", fgets($this->sock, 512));
        
        if (!preg_match("/^[23]/", $response)) {
            fputs($this->sock, "QUIT\r\n");
            fgets($this->sock, 512);
            $this->log_write("Error: Remote host returned \"" . $response . "\"\n");
            return FALSE;
        }
        return TRUE;
    }

    private function smtp_putcmd($cmd, $arg = "")
    {
        if ($arg != "")
        {
            if ($cmd == "")
            {
                $cmd = $arg;
            }
            else 
            {
                $cmd = $cmd . " " . $arg;
            }
        }

        fputs($this->sock, $cmd . "\r\n");

        return $this->smtp_ok();
    }

    private function smtp_error($string)
    {
        $this->log_write("Error: Error occurred while " . $string . ".\n");
        return FALSE;
    }

    private function log_write($message)
    {
        $this->error_log($message);

        return TRUE;
    }

    private function strip_comment($address)
    {
        $comment = "/\\([^()]*\\)/";
        while (preg_match($comment, $address)) {
            $address = preg_replace($comment, "", $address);
        }

        return $address;
    }

    private function get_address($address)
    {
        return preg_replace("/^.*<(.+)>.*$/", "\\1", preg_replace("/([ \t\r\n])+/", "", $address));
    }

    private function error_log($message)
    {
        throw new \Exception($message);
    }
}