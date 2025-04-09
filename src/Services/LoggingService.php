<?php

namespace SlimApp\Services;


use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Formatter\JsonFormatter;



class LoggingService extends AbstractService
// Logging service based on Monolog library.
// It can do whatever Monolog can.
{
    const CONFIG_SCHEMA = 'schemas/logging.json';

    protected Logger $logger;

    protected function initialize() 
    {
        // create logger
        $this->logger = new Logger($this->config->name ?? 'SLIMAPP');

        // read handlers from the config
        // - either an array or comma-separated string may be given
        $handlers = (is_array($this->config->handlers ?? []))
            ? $this->config->handlers ?? []
            : array_map('trim', explode(',', $this->config->handlers));

        // create each handler and set its params based on the config
        foreach($handlers as $handler) {
            $h = NULL;
            switch ($handler) {
                case 'null': 
                    $h = new NullHandler(); 
                    break;
                case 'file': 
                    if (!$this->config->file) throw new \RuntimeException('Configuration is required for '.static::class.' (file handler)');
                    $h = new StreamHandler(
                        $this->config->file->path, 
                        level:$this->config->mail->level ?? 'error'
                    ); 
                    break;
                case 'mail': 
                    if (!$this->config->mail) throw new \RuntimeException('Configuration is required for '.static::class.' (mail handler)');
                    $h = new NativeMailerHandler(
                        to:$this->config->mail->to, subject:$this->config->mail->subject, 
                        from:$this->config->mail->from, 
                        level:$this->config->mail->level ?? 'error'
                    );
                    break;
            }
            // assign the handler to the logger
            $this->logger->pushHandler($h);
        }
    }


    public function log($level, string|\Stringable $message, array $context = [], ?\Throwable $e = NULL, bool $include_post_request_data=FALSE): void
    {
        if (isset($e)) {
            $context['exception_class'] = $e::class;
            $context['exception_message'] = $e->getMessage();
            $context['exception_code'] = $e->getCode();
            $context['exception_file'] = $e->getFile();
            $context['exception_line'] = $e->getLine();
            $context['exception_trace'] = $e->getTrace();
        }
        $context['client_ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $context['client_referer_url'] = $_SERVER['HTTP_REFERER'] ?? '';
        $context['client_request_uri'] = $_SERVER['REQUEST_URI'] ?? '';

        if ($include_post_request_data) {
            $context['post_data'] = ($_SERVER['CONTENT_LENGTH']??0 < 1000) ? file_get_contents('php://input') : '(data too big)';
        }
        
        $this->logger->log($level, $message, $context);
    }


    public function debug(string|\Stringable $message, array $context = [], ?\Throwable $e = NULL, bool $include_post_request_data=FALSE): void
    {
        $this->log(\Monolog\Level::Debug, $message, $context, $e);
    }


    public function info(string|\Stringable $message, array $context = [], ?\Throwable $e = NULL, bool $include_post_request_data=FALSE): void
    {
        $this->log(\Monolog\Level::Info, $message, $context, $e);
    }


    public function notice(string|\Stringable $message, array $context = [], ?\Throwable $e = NULL, bool $include_post_request_data=FALSE): void
    {
        $this->log(\Monolog\Level::Notice, $message, $context, $e);
    }


    public function warning(string|\Stringable $message, array $context = [], ?\Throwable $e = NULL, bool $include_post_request_data=FALSE): void
    {
        $this->log(\Monolog\Level::Warning, $message, $context, $e);
    }


    public function error(string|\Stringable $message, array $context = [], ?\Throwable $e = NULL, bool $include_post_request_data=FALSE): void
    {
        $this->log(\Monolog\Level::Error, $message, $context, $e);
    }

    public function critical(string|\Stringable $message, array $context = [], ?\Throwable $e = NULL, bool $include_post_request_data=FALSE): void
    {
        $this->log(\Monolog\Level::Critical, $message, $context, $e);
    }


}

