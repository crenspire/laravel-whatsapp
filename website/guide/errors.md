# Errors and retries

## Exceptions

Failed API calls throw an exception from `Crenspire\Whatsapp\Exceptions`. Each kind of failure has its own class, and all of them extend `WhatsappException`, so you can catch as broadly or narrowly as you need.

| Exception | When | What to do |
|---|---|---|
| `CustomerServiceWindowException` | You sent a regular message more than 24 hours after the customer last messaged you | Send a template instead |
| `RateLimitException` | You hit your own `WHATSAPP_RATE_LIMIT` or one of Meta's limits | Slow down and try later |
| `UndeliverableMessageException` | The recipient can't get the message, for example because they aren't on WhatsApp or opted out of marketing | Don't retry |
| `TemplateException` | The template doesn't exist in that language, isn't approved, or the parameters don't match | Check the template name, language and parameter count |
| `AuthenticationException` | The access token is invalid, expired, or missing a permission | Generate a new token |
| `InvalidRequestException` | A parameter is missing or invalid | Fix the request |
| `AccountException` | Your business account or phone number is restricted, locked, or has a payment problem | Check WhatsApp Manager |
| `ServiceUnavailableException` | Meta had a temporary problem | Try again later |
| `ConnectionException` | The API couldn't be reached | Try again later |

```php
use Crenspire\Whatsapp\Exceptions\CustomerServiceWindowException;
use Crenspire\Whatsapp\Exceptions\UndeliverableMessageException;
use Crenspire\Whatsapp\Exceptions\WhatsappException;

try {
    Whatsapp::sendTextMessage($phone, $text);
} catch (CustomerServiceWindowException $e) {
    Whatsapp::sendTemplateMessage($phone, 'follow_up', [$name]);
} catch (UndeliverableMessageException $e) {
    $customer->update(['whatsapp_opted_out' => true]);
} catch (WhatsappException $e) {
    report($e);
}
```

## Error details

| Method | Returns |
|---|---|
| `getCode()` | The HTTP status of the failed response |
| `getErrorCode()` | Meta's error code, such as `131047` |
| `getErrorDetails()` | Meta's explanation of the error |
| `getFbtraceId()` | The trace ID to quote when contacting Meta support |
| `getError()` | The full error object from the response |

Meta lists every error code in its [error codes reference](https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes). Codes the package doesn't recognize throw the base `WhatsappException`.

## Retries

Requests that fail for a temporary reason are retried automatically, up to `WHATSAPP_RETRY_TIMES` attempts in total (3 by default). The wait starts at `WHATSAPP_RETRY_SLEEP` milliseconds and doubles each time, and a `Retry-After` header from Meta is respected. Set `WHATSAPP_RETRY_TIMES=1` to turn retries off.

What gets retried depends on whether repeating the request is safe:

| Failure | Sending a message | Other requests |
|---|---|---|
| Rate limit (HTTP 429, or Meta codes such as `130429` and `131056`) | Retried | Retried |
| Temporary Meta error with a retryable code (`131000`, `131016`, `133004`, ...) | Retried | Retried |
| Server error without a retryable code | **Not retried** | Retried |
| Timeout or connection failure | **Not retried** | Retried |

Meta's API has no way to detect a duplicate send. If sending a message times out, or fails with an unexplained server error, the message may already be on its way, so retrying could deliver it twice. Those failures throw instead, and you decide what to do.

## Rate limiting

Each phone number can send `WHATSAPP_RATE_LIMIT` messages per minute (30 by default). Going over throws a `RateLimitException` with code 429 without calling the API.

The limit is tracked in your application cache. If you send from several servers or queue workers, use a shared cache store such as Redis so they share one limit.
