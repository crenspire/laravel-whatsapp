<?php

use Crenspire\Whatsapp\Exceptions\AuthenticationException;
use Crenspire\Whatsapp\Exceptions\ConnectionException;
use Crenspire\Whatsapp\Exceptions\CustomerServiceWindowException;
use Crenspire\Whatsapp\Exceptions\ErrorCodes;
use Crenspire\Whatsapp\Exceptions\InvalidRequestException;
use Crenspire\Whatsapp\Exceptions\RateLimitException;
use Crenspire\Whatsapp\Exceptions\ServiceUnavailableException;
use Crenspire\Whatsapp\Exceptions\TemplateException;
use Crenspire\Whatsapp\Exceptions\UndeliverableMessageException;
use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\Http\GraphClient;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function metaError(int $code, int $status = 400, string $message = 'Something failed', ?string $details = null): PromiseInterface
{
    return Http::response(['error' => array_filter([
        'message' => $message,
        'type' => 'OAuthException',
        'code' => $code,
        'error_data' => $details ? ['messaging_product' => 'whatsapp', 'details' => $details] : null,
        'fbtrace_id' => 'trace_123',
    ])], $status);
}

function client(int $times = 3): GraphClient
{
    return new GraphClient(['Authorization' => 'Bearer test_token'], 30, $times, 0);
}

// Error mapping

it('maps Meta error codes to exceptions', function (int $code, string $class) {
    Http::fake(['*' => metaError($code)]);

    expect(fn () => client(1)->get('https://graph.facebook.com/v20.0/x'))->toThrow($class);
})->with([
    [131047, CustomerServiceWindowException::class],
    [190, AuthenticationException::class],
    [210, AuthenticationException::class],
    [130429, RateLimitException::class],
    [131056, RateLimitException::class],
    [131026, UndeliverableMessageException::class],
    [132001, TemplateException::class],
    [100, InvalidRequestException::class],
    [131016, ServiceUnavailableException::class],
    [999999, WhatsappException::class],
]);

it('falls back to the HTTP status for errors without a known code', function () {
    expect(ErrorCodes::exceptionFor(null, 401))->toBe(AuthenticationException::class)
        ->and(ErrorCodes::exceptionFor(null, 429))->toBe(RateLimitException::class)
        ->and(ErrorCodes::exceptionFor(null, 503))->toBe(ServiceUnavailableException::class)
        ->and(ErrorCodes::exceptionFor(null, 400))->toBe(WhatsappException::class);
});

it('exposes Meta error details on the exception', function () {
    Http::fake(['*' => metaError(131047, 400, 'Re-engagement message', 'Message failed to send because more than 24 hours have passed')]);

    try {
        client(1)->post('https://graph.facebook.com/v20.0/123/messages', [], 'send WhatsApp message');
        $this->fail('Expected an exception');
    } catch (CustomerServiceWindowException $e) {
        expect($e->getMessage())->toStartWith('Failed to send WhatsApp message: Re-engagement message (Message failed to send because more than 24 hours have passed)')
            ->and($e->getCode())->toBe(400)
            ->and($e->getHttpStatus())->toBe(400)
            ->and($e->getErrorCode())->toBe(131047)
            ->and($e->getErrorDetails())->toBe('Message failed to send because more than 24 hours have passed')
            ->and($e->getFbtraceId())->toBe('trace_123')
            ->and($e)->toBeInstanceOf(WhatsappException::class);
    }
});

// Retries

it('retries rate limited requests and returns the eventual response', function () {
    Http::fakeSequence()
        ->push(['error' => ['code' => 130429, 'message' => 'Rate limit hit']], 400)
        ->push(['error' => ['message' => 'Too many requests']], 429)
        ->push(['messages' => [['id' => 'wamid.1']]]);

    $response = client()->post('https://graph.facebook.com/v20.0/123/messages', ['to' => '1']);

    expect($response['messages'][0]['id'])->toBe('wamid.1');
    Http::assertSentCount(3);
});

it('retries server errors for idempotent requests', function () {
    Http::fakeSequence()->push('', 500)->push(['id' => 'media_1']);

    expect(client()->get('https://graph.facebook.com/v20.0/media_1')['id'])->toBe('media_1');
    Http::assertSentCount(2);
});

it('does not retry server errors when sending a message, to avoid duplicates', function () {
    Http::fakeSequence()->push(['error' => ['message' => 'Unknown']], 500)->push(['messages' => [['id' => 'wamid.1']]]);

    expect(fn () => makeService()->sendTextMessage('15551234567', 'Hello'))
        ->toThrow(ServiceUnavailableException::class);

    Http::assertSentCount(1);
});

it('retries a message send when Meta confirms a retryable failure', function () {
    Http::fakeSequence()
        ->pushResponse(metaError(131000, 500, 'Something went wrong'))
        ->push(['messages' => [['id' => 'wamid.1']]]);

    expect(makeService()->sendTextMessage('15551234567', 'Hello')['messages'][0]['id'])->toBe('wamid.1');
    Http::assertSentCount(2);
});

it('does not retry errors that will fail again', function () {
    Http::fake(['*' => metaError(131047)]);

    expect(fn () => client()->post('https://graph.facebook.com/v20.0/123/messages'))->toThrow(CustomerServiceWindowException::class);
    Http::assertSentCount(1);
});

it('stops after the configured number of attempts', function () {
    Http::fake(['*' => Http::response('', 503)]);

    expect(fn () => client(2)->get('https://graph.facebook.com/v20.0/x'))->toThrow(ServiceUnavailableException::class);
    Http::assertSentCount(2);
});

it('does not retry when retries are disabled', function () {
    Http::fake(['*' => Http::response('', 503)]);

    expect(fn () => makeService(['retry' => ['times' => 1, 'sleep' => 0]])->getMediaInfo('m1'))
        ->toThrow(ServiceUnavailableException::class);
    Http::assertSentCount(1);
});

it('retries connection errors only for idempotent requests', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new HttpConnectionException('cURL error 28: timed out');
    });

    expect(fn () => client()->get('https://graph.facebook.com/v20.0/x'))->toThrow(ConnectionException::class);
    expect($attempts)->toBe(3);

    $attempts = 0;
    expect(fn () => client()->post('https://graph.facebook.com/v20.0/123/messages'))->toThrow(ConnectionException::class);
    expect($attempts)->toBe(1);
});

it('uses Retry-After and exponential backoff for the delay', function () {
    $delay = new ReflectionMethod(GraphClient::class, 'retryDelay');
    $client = new GraphClient([], 30, 3, 200);

    $response = new Illuminate\Http\Client\Response(new Response(429, ['Retry-After' => '2']));

    expect($delay->invoke($client, 1))->toBe(200)
        ->and($delay->invoke($client, 3))->toBe(800)
        ->and($delay->invoke($client, 20))->toBe(10_000)
        ->and($delay->invoke($client, 1, new RequestException($response)))->toBe(2000);
});

it('throws the package rate limit exception when the local limit is reached', function () {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
    $service = makeService(['rate_limit' => 1]);
    $service->sendTextMessage('15551234567', 'First');

    expect(fn () => $service->sendTextMessage('15551234567', 'Second'))->toThrow(RateLimitException::class);
});
