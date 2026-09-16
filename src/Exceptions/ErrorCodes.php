<?php

namespace Crenspire\Whatsapp\Exceptions;

/**
 * Maps Graph API error codes to exception classes and retry behavior
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes
 */
final class ErrorCodes
{
    /**
     * Meta error codes grouped by the exception thrown for them
     *
     * @var array<class-string<WhatsappException>, int[]>
     */
    public const EXCEPTIONS = [
        AuthenticationException::class => [0, 10, 190, 131005],
        RateLimitException::class => [4, 80007, 130429, 131048, 131056, 131064],
        CustomerServiceWindowException::class => [131047],
        UndeliverableMessageException::class => [130403, 130472, 130497, 131021, 131026, 131049, 131050],
        TemplateException::class => [132000, 132001, 132005, 132007, 132012, 132015, 132016, 132018, 132068, 132069],
        InvalidRequestException::class => [100, 131008, 131009, 131051, 131052, 131053, 135000],
        AccountException::class => [
            3, 33, 368, 131031, 131037, 131042, 131045, 131063,
            133000, 133005, 133006, 133008, 133009, 133010, 133015, 133016, 134011,
        ],
        ServiceUnavailableException::class => [2, 131000, 131016, 131057, 133004],
    ];

    /**
     * Meta error codes that mean the request definitely failed and can be retried after a wait
     *
     * @var int[]
     */
    public const RETRYABLE = [2, 4, 80007, 130429, 131000, 131016, 131056, 131057, 133004];

    /**
     * Get the exception class for a Meta error code
     *
     * Permission errors (200-299) are treated as authentication failures, and
     * unknown codes fall back to the HTTP status.
     *
     * @return class-string<WhatsappException>
     */
    public static function exceptionFor(int|string|null $code, ?int $httpStatus = null): string
    {
        if ($code !== null) {
            $code = (int) $code;

            foreach (self::EXCEPTIONS as $class => $codes) {
                if (in_array($code, $codes, true)) {
                    return $class;
                }
            }

            if ($code >= 200 && $code <= 299) {
                return AuthenticationException::class;
            }
        }

        return match (true) {
            $httpStatus === 401, $httpStatus === 403 => AuthenticationException::class,
            $httpStatus === 429 => RateLimitException::class,
            $httpStatus !== null && $httpStatus >= 500 => ServiceUnavailableException::class,
            default => WhatsappException::class,
        };
    }

    /**
     * Whether a failed request should be retried
     *
     * The API has no idempotency keys, so a request that may have been
     * processed (a 5xx without a retryable error code) is only retried when
     * repeating it is harmless.
     *
     * @param  bool  $idempotent  Whether sending the request twice is harmless
     */
    public static function isRetryable(int|string|null $code, int $httpStatus, bool $idempotent = true): bool
    {
        if ($code !== null && in_array((int) $code, self::RETRYABLE, true)) {
            return true;
        }

        if ($httpStatus === 429) {
            return true;
        }

        return $idempotent && $httpStatus >= 500;
    }
}
