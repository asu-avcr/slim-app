<?php

namespace SlimApp\Services;


use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Formatter\JsonFormatter;



class LoggingService
// Logging service based on Monolog library.
// It can do whatever Monolog can.
{
    protected Logger $logger;


    public function __construct(?object $logging_conf) 
    {
        // create logger
        $this->logger = new Logger($logging_conf->name ?? 'SLIMAPP');

        if (!$logging_conf) return;

        // read handlers from the config
        // - either an array or comma-separated string may be given
        $handlers = (is_array($logging_conf->handlers ?? []))
            ? $logging_conf->handlers ?? []
            : array_map('trim', explode(',', $logging_conf->handlers));

        // create each handler and set its params based on the config
        foreach($handlers as $handler) {
            $h = NULL;
            switch ($handler) {
                case 'null': $h = new NullHandler(); break;

                case 'file': $h = new StreamHandler(
                        $logging_conf->file->path, 
                        level:$logging_conf->mail->level ?? 'error'
                    ); 
                    break;
                
                case 'mail': $h = new NativeMailerHandler(
                        to:$logging_conf->mail->to, subject:$logging_conf->mail->subject, 
                        from:$logging_conf->mail->from, 
                        level:$logging_conf->mail->level ?? 'error'
                    );
                    break;
            }
            // assign the handler to the logger
            $this->logger->pushHandler($h);
        }
    }


    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }


    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }


    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }


    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }


    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }


    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }


}

