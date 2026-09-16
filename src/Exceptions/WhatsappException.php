<?php

namespace Crenspire\Whatsapp\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * Base exception for WhatsApp API failures
 *
 * The exception code is the HTTP status of the failed response. Meta's own
 * error code, subcode and details are available through the getters.
 */
class WhatsappException extends Exception
{
    /**
     * The error object from the Graph API response, if any
     */
    protected array $error = [];

    /**
     * The HTTP status of the failed response, if any
     */
    protected ?int $httpStatus = null;

    /**
     * Create a new exception instance
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  string|null  $body  Optional response body to append to message
     */
    public function __construct(string $message, int $code = 0, ?string $body = null)
    {
        parent::__construct($message.($body ? " Response: {$body}" : ''), $code);
    }

    /**
     * Create the most specific exception for a failed Graph API response
     *
     * @param  Response  $response  The failed response
     * @param  string  $action  What was being attempted, e.g. "send WhatsApp message"
     */
    public static function fromResponse(Response $response, string $action): self
    {
        $error = $response->json('error');
        $error = is_array($error) ? $error : [];

        $class = ErrorCodes::exceptionFor($error['code'] ?? null, $response->status());
        $details = $error['error_data']['details'] ?? null;
        $message = $error['message'] ?? 'Unknown error';

        if (is_string($details) && $details !== '' && ! str_contains($message, $details)) {
            $message .= " ({$details})";
        }

        $exception = new $class("Failed to {$action}: {$message}", $response->status(), $response->body());
        $exception->error = $error;
        $exception->httpStatus = $response->status();

        return $exception;
    }

    /**
     * Meta's error code, e.g. 131047 for a message outside the customer service window
     */
    public function getErrorCode(): ?int
    {
        return isset($this->error['code']) ? (int) $this->error['code'] : null;
    }

    /**
     * Meta's error subcode, if provided
     */
    public function getErrorSubcode(): ?int
    {
        return isset($this->error['error_subcode']) ? (int) $this->error['error_subcode'] : null;
    }

    /**
     * The human-readable error details from Meta, if provided
     */
    public function getErrorDetails(): ?string
    {
        return $this->error['error_data']['details'] ?? null;
    }

    /**
     * The trace ID to quote when contacting Meta support
     */
    public function getFbtraceId(): ?string
    {
        return $this->error['fbtrace_id'] ?? null;
    }

    /**
     * The full error object from the Graph API response
     */
    public function getError(): array
    {
        return $this->error;
    }

    /**
     * The HTTP status of the failed response, if the failure came from the API
     */
    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }
}
