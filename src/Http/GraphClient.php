<?php

namespace Crenspire\Whatsapp\Http;

use Crenspire\Whatsapp\Exceptions\ConnectionException;
use Crenspire\Whatsapp\Exceptions\ErrorCodes;
use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends requests to the Graph API
 *
 * Every request uses the same headers, timeout and retry policy, and failed
 * responses are turned into the matching WhatsappException subclass.
 */
class GraphClient
{
    /**
     * Longest time to wait before a retry, in milliseconds
     */
    private const MAX_RETRY_DELAY = 10_000;

    /**
     * @param  array  $headers  Headers sent with every request
     * @param  int  $timeout  Request timeout in seconds
     * @param  int  $retryTimes  Total attempts for retryable failures (1 disables retries)
     * @param  int  $retrySleep  Delay before the first retry in milliseconds, doubled on each retry
     */
    public function __construct(
        private array $headers = [],
        private int $timeout = 30,
        private int $retryTimes = 3,
        private int $retrySleep = 200,
    ) {}

    /**
     * Create a client from the package configuration
     */
    public static function fromConfig(array $config, array $headers = []): self
    {
        return new self(
            $headers,
            (int) ($config['timeout'] ?? 30),
            max(1, (int) ($config['retry']['times'] ?? 3)),
            max(0, (int) ($config['retry']['sleep'] ?? 200)),
        );
    }

    /**
     * Get a copy of the client with a different timeout
     */
    public function withTimeout(int $seconds): self
    {
        $client = clone $this;
        $client->timeout = $seconds;

        return $client;
    }

    /**
     * Get the headers sent with every request
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Send a GET request and decode the JSON response
     */
    public function get(string $url, array $query = [], string $action = 'call the WhatsApp API'): array
    {
        return $this->send('get', $this->withQuery($url, $query), [], $action)->json() ?? [];
    }

    /**
     * Send a JSON POST request and decode the JSON response
     *
     * @param  bool  $idempotent  Whether repeating the request is harmless. Non-idempotent
     *                            requests, such as sending a message, are only retried when
     *                            Meta confirms the request failed.
     */
    public function post(string $url, array $data = [], string $action = 'call the WhatsApp API', bool $idempotent = false): array
    {
        return $this->send('post', $url, $data, $action, idempotent: $idempotent)->json() ?? [];
    }

    /**
     * Send a DELETE request and decode the JSON response
     */
    public function delete(string $url, array $query = [], string $action = 'call the WhatsApp API'): array
    {
        return $this->send('delete', $this->withQuery($url, $query), [], $action)->json() ?? [];
    }

    /**
     * Send a multipart POST request with a file and decode the JSON response
     *
     * @param  array  $file  ['name' => field name, 'contents' => file contents, 'filename' => ..., 'headers' => [...]]
     */
    public function upload(string $url, array $file, array $data = [], string $action = 'upload media'): array
    {
        return $this->send('post', $url, $data, $action, function (PendingRequest $request) use ($file) {
            return $request->attach($file['name'], $file['contents'], $file['filename'], $file['headers'] ?? []);
        }, withoutContentType: true, idempotent: true)->json() ?? [];
    }

    /**
     * Download a file and return its contents
     */
    public function download(string $url, string $action = 'download media'): string
    {
        return $this->send('get', $url, [], $action)->body();
    }

    /**
     * Send a POST request with a raw body, e.g. for resumable uploads
     */
    public function postRaw(string $url, string $body, string $contentType, array $headers = [], string $action = 'call the WhatsApp API'): array
    {
        return $this->send('post', $url, [], $action, function (PendingRequest $request) use ($body, $contentType) {
            return $request->withBody($body, $contentType);
        }, withoutContentType: true, idempotent: true, headers: $headers)->json() ?? [];
    }

    /**
     * Send a request, retrying temporary failures, and throw for failed responses
     *
     * @param  callable|null  $prepare  Receives the pending request to add a body or files
     * @param  bool|null  $idempotent  Whether repeating the request is harmless; defaults to true for GET and DELETE
     * @param  array  $headers  Headers that replace the client's headers of the same name for this request
     *
     * @throws WhatsappException
     */
    public function send(string $method, string $url, array $data, string $action, ?callable $prepare = null, bool $withoutContentType = false, ?bool $idempotent = null, array $headers = []): Response
    {
        $idempotent ??= in_array($method, ['get', 'delete'], true);

        // Headers are appended by the HTTP client, so drop any being replaced
        $replaced = array_map('strtolower', array_keys($headers));

        if ($withoutContentType) {
            $replaced[] = 'content-type';
        }

        $headers = array_merge(
            array_filter($this->headers, fn ($name) => ! in_array(strtolower($name), $replaced, true), ARRAY_FILTER_USE_KEY),
            $headers
        );

        $request = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelay(...), fn (Throwable $e) => $this->shouldRetry($e, $idempotent), throw: false);

        if ($prepare !== null) {
            $request = $prepare($request);
        }

        try {
            $response = match ($method) {
                'get' => $request->get($url),
                'delete' => $request->delete($url),
                default => $request->{$method}($url, $data),
            };
        } catch (HttpConnectionException $e) {
            throw new ConnectionException("Failed to {$action}: {$e->getMessage()}", 0);
        }

        if ($response->failed()) {
            throw WhatsappException::fromResponse($response, $action);
        }

        return $response;
    }

    /**
     * Decide whether a failed attempt should be retried
     *
     * A connection error may happen after the API processed the request, so
     * it's only retried when repeating the request is harmless.
     */
    private function shouldRetry(Throwable $exception, bool $idempotent): bool
    {
        if ($exception instanceof HttpConnectionException) {
            return $idempotent;
        }

        if ($exception instanceof RequestException) {
            return ErrorCodes::isRetryable(
                $exception->response->json('error.code'),
                $exception->response->status(),
                $idempotent
            );
        }

        return false;
    }

    /**
     * Get the delay before the next attempt, honoring Retry-After when present
     */
    private function retryDelay(int $attempt, ?Throwable $exception = null): int
    {
        $retryAfter = $exception instanceof RequestException
            ? $exception->response->header('Retry-After')
            : '';

        if (is_numeric($retryAfter)) {
            return min((int) $retryAfter * 1000, self::MAX_RETRY_DELAY);
        }

        return min($this->retrySleep * (2 ** ($attempt - 1)), self::MAX_RETRY_DELAY);
    }

    /**
     * Append query parameters to a URL
     */
    private function withQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }
}
