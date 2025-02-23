<?php

namespace SlimApp\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
// MailService provides SMTP cpmmunication and delivering of emails.
{
    private ?object $mail_conf;

    public function __construct(?object $mail_conf) 
    {
        $this->mail_conf = $mail_conf;
    }


    public function send($subject, $body, $to, $cc=NULL, $bcc=NULL, $replyto=NULL) 
    // Send email to recipients.
    // To, Cc, Bcc parameters can be either a string or an array of strings or a NULL value.
    // In case of an array, each item is added as a recipient. ReplyTo must be an email or NULL.
    {
        if (!$this->mail_conf) {
            throw \Exception("SlimApp\Services\MailService has been used without configuration");
        }

        $email = new PHPMailer();
        $email->CharSet = "UTF-8";
        if ($this->mail_conf->transport == 'smtp') {
            $email->isSMTP(); // send using SMTP
            $email->Host       = $this->mail_conf->host;
            $email->Port       = $this->mail_conf->port ?? 587;                                
            $email->SMTPAuth   = boolval($this->mail_conf->smtp_auth ?? FALSE);
            $email->Username   = $this->mail_conf->username;
            $email->Password   = $this->mail_conf->password;
            $email->SMTPSecure = $this->mail_conf->encryption ?? '';
        }
        $email->setFrom($this->mail_conf->sender_email, $this->mail_conf->sender_name??'');
        $email->Subject= $subject;
        $email->Body = str_replace("\0", "", $body)."\n";    // remove null bytes

        if (!empty($to)) {
            $list = is_array($to) ? $to : explode(',',$to);
            foreach ($list as $addr) $email->AddAddress(trim($addr));
        }
        if (!empty($cc)) {
            $list = is_array($cc) ? $cc : explode(',',$cc);
            foreach ($list as $addr) $email->AddAddress(trim($addr));
        }
        if (!empty($bcc)) {
            $list = is_array($bcc) ? $bcc : explode(',',$bcc);
            foreach ($list as $addr) $email->AddAddress(trim($addr));
        }
        if ($replyto) {
            $email->AddReplyTo($replyto);
        }

        $email->Send();
    }


    public function send_template(string $template, array $params, $to, $cc=NULL, $bcc=NULL, $replyto=NULL) 
    {
        // separate headers and body
        list($headers, $body) = explode("\n\n", $template, 2);
        // parse header 'key: value' pairs (must be on a single line)
        $headers_parsed = array_reduce(explode(PHP_EOL,$headers), function ($result, $item) {
            list($key, $val) = explode(":", $item, 2);
            $result[strtolower(trim($key))] = trim($val);
            return $result;
        }, array());

        $tmpl_engine = new \StringTemplate\SprintfEngine;
        $this->send(
            $tmpl_engine->render($headers_parsed['subject'], $params), 
            $tmpl_engine->render($body, $params), 
            $to, $cc, $bcc, $replyto
        );
    }


    public function send_error_report(\Throwable $e, string $class, string $email) 
    // Format a and send an exception error report to given email.
    {
        $this->send(
            'Error report - ' . $e::class, 
            "Error: " . $e->getMessage() . "\n" .
            "Class: " . $class . "\n" .
            "\n" .
            "File: " . $e->getFile() . "\n" .
            "Line: " . $e->getLine() . "\n" .
            "\n" .
            $e->getTraceAsString() .
            "\n",
            $email
        );
    }

}

