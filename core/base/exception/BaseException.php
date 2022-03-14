<?php

namespace core\base\exception;

use core\base\controller\BaseMethods;

class BaseException extends \Exception
{
    use BaseMethods;
    
    protected $messages;
    
    public function __construct(?string $message = '', ?int $code = 0)
    {
        parent::__construct($message, $code);
        
        $this->messages = include MESSAGES_PATH . 'errorMessages.php';
        
        $error = $this->getMessage() . ' <file> ' . $this->getFile() . ' [row] ' . $this->getLine() . "\n";
        
        if ($this->messages[$this->getCode()]) $this->message = $this->messages[$this->getCode()];
        
        $this->toLog($error, null, 'Exception');
    }
}