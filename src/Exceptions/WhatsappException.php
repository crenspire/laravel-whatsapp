<?php

namespace Crenspire\Whatsapp\Exceptions;

use Exception;

/**
 * WhatsApp Exception
 * 
 * This exception is thrown when WhatsApp API operations fail or encounter errors.
 * 
 * @package Crenspire\Whatsapp\Exceptions
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class WhatsappException extends Exception
{
    /**
     * Create a new exception instance
     * 
     * @param string $message The exception message
     * @param int $code The exception code
     * @param string|null $body Optional response body to append to message
     */
    public function __construct(string $message, int $code = 0, ?string $body = null)
    {
        parent::__construct($message . ($body ? " Response: {$body}" : ''), $code);
    }
}
