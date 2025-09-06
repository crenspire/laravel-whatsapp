<?php

namespace Crenspire\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\Events\MessageSent;
use Crenspire\Whatsapp\Events\MessageFailed;

/**
 * WhatsApp Business Cloud API Service
 *
 * This service provides a comprehensive interface for interacting with the WhatsApp Business Cloud API,
 * including sending various types of messages, handling media uploads/downloads, and managing webhooks.
 *
 * @package Crenspire\Whatsapp
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class WhatsappService
{
    /**
     * Service configuration array
     *
     * @var array
     */
    protected array $config;

    /**
     * Create a new WhatsApp service instance
     *
     * @param array $config The service configuration array
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        // Ensure media storage directory exists
        if (!is_dir($this->config['media_storage'])) {
            mkdir($this->config['media_storage'], 0755, true);
        }
    }

    /**
     * Get tenant-specific configuration or default configuration
     *
     * @param string|null $tenantId The tenant ID to get configuration for
     * @return array The tenant configuration array
     */
    protected function tenantConfig(?string $tenantId = null): array
    {
        if ($tenantId && isset($this->config['tenants'][$tenantId])) {
            $tenant = $this->config['tenants'][$tenantId];
            return [
                'phone_number_id' => $tenant['phone_number_id'],
                'access_token' => $tenant['access_token'],
                'headers' => $tenant['headers'] ?? [],
                'language' => $tenant['language'] ?? $this->config['default_language'] ?? 'en-US',
            ];
        }

        return [
            'phone_number_id' => $this->config['phone_number_id'],
            'access_token'    => $this->config['access_token'],
            'headers' => [],
            'language' => $this->config['default_language'] ?? 'en-US',
        ];
    }

    /**
     * Build headers for HTTP requests
     *
     * @param array $tenantConfig The tenant configuration
     * @param array $customHeaders Custom headers to merge
     * @return array The merged headers array
     */
    protected function buildHeaders(array $tenantConfig, array $customHeaders = []): array
    {
        $headers = array_merge(
            $this->config['default_headers'] ?? [],
            $tenantConfig['headers'] ?? [],
            $customHeaders
        );

        // Ensure Authorization header is set if access token is available
        if (!empty($tenantConfig['access_token'])) {
            $headers['Authorization'] = 'Bearer ' . $tenantConfig['access_token'];
        }

        return $headers;
    }

    /**
     * Get language for a request
     *
     * @param array $tenantConfig The tenant configuration
     * @param string|null $customLanguage Custom language override
     * @return string The language code
     */
    protected function getLanguage(array $tenantConfig, ?string $customLanguage = null): string
    {
        return $customLanguage ?? $tenantConfig['language'] ?? $this->config['default_language'] ?? 'en-US';
    }

    /**
     * Send a WhatsApp message
     *
     * This is the core method for sending any type of WhatsApp message. It handles
     * rate limiting, phone number validation, and dispatches appropriate events.
     *
     * @param string $to The recipient phone number (with country code)
     * @param array $message The message payload array
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @param array $customHeaders Optional custom headers for this request
     * @param string|null $language Optional language override (ISO 639-1 format)
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendMessage(string $to, array $message, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        $this->validatePhoneNumber($to);

        $tenant = $this->tenantConfig($tenantId);
        $key = "whatsapp-send:{$tenant['phone_number_id']}";

        if (! RateLimiter::attempt($key, $perMinute = $this->config['rate_limit'], fn () => true)) {
            throw new WhatsappException("Rate limit exceeded. Try again later.");
        }

        $url = "{$this->config['base_uri']}/{$tenant['phone_number_id']}/messages";

        $payload = array_merge(['messaging_product' => 'whatsapp', 'to' => $to], $message);

        Log::info('Sending WhatsApp message', [
            'to' => $to,
            'tenant' => $tenantId,
            'message_type' => $message['type'] ?? 'unknown'
        ]);

        $headers = $this->buildHeaders($tenant, $customHeaders);
        
        $response = Http::withHeaders($headers)
                        ->timeout(30)
                        ->post($url, $payload);

        if ($response->failed()) {
            $errorData = $response->json();
            Log::error('WhatsApp message failed', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $errorData
            ]);

            event(new MessageFailed($to, $errorData));
            throw new WhatsappException(
                "Failed to send WhatsApp message: " . ($errorData['error']['message'] ?? 'Unknown error'),
                $response->status(),
                $response->body()
            );
        }

        $data = $response->json();
        event(new MessageSent($data['messages'][0]['id'] ?? null, $to, $data));

        return $data;
    }

    /**
     * Send a text message
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $text The text message content
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendTextMessage(string $to, string $text, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        return $this->sendMessage($to, [
            'type' => 'text',
            'text' => ['body' => $text]
        ], $tenantId, $customHeaders, $language);
    }

    /**
     * Send a media message (image, video, document, audio)
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $mediaId The media ID from WhatsApp (uploaded via uploadMedia method)
     * @param string $type The media type (image, video, document, audio)
     * @param string|null $caption Optional caption for the media
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendMediaMessage(string $to, string $mediaId, string $type, ?string $caption = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        $message = [
            'type' => $type,
            $type => [
                'id' => $mediaId
            ]
        ];

        if ($caption && in_array($type, ['image', 'video', 'document'])) {
            $message[$type]['caption'] = $caption;
        }

        return $this->sendMessage($to, $message, $tenantId, $customHeaders, $language);
    }

    /**
     * Send a template message
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $templateName The name of the approved template
     * @param array $parameters Optional parameters to fill template placeholders
     * @param string $language The language code (default: en_US)
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendTemplateMessage(string $to, string $templateName, array $parameters = [], ?string $language = null, ?string $tenantId = null, array $customHeaders = []): array
    {
        $tenant = $this->tenantConfig($tenantId);
        $language = $this->getLanguage($tenant, $language);
        
        $components = [];

        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn($param) => ['type' => 'text', 'text' => $param], $parameters)
            ];
        }

        return $this->sendMessage($to, [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components
            ]
        ], $tenantId, $customHeaders, $language);
    }

    /**
     * Send an interactive message (buttons, lists, etc.)
     *
     * @param string $to The recipient phone number (with country code)
     * @param array $interactive The interactive message payload
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendInteractiveMessage(string $to, array $interactive, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        return $this->sendMessage($to, [
            'type' => 'interactive',
            'interactive' => $interactive
        ], $tenantId, $customHeaders, $language);
    }

    /**
     * Send a button message
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $bodyText The main message text
     * @param array $buttons Array of button configurations [['id' => 'btn1', 'title' => 'Button 1'], ...]
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendButtonMessage(string $to, string $bodyText, array $buttons, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $bodyText],
            'action' => [
                'buttons' => array_map(function($button, $index) {
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'] ?? "btn_{$index}",
                            'title' => $button['title']
                        ]
                    ];
                }, $buttons, array_keys($buttons))
            ]
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }

        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->sendInteractiveMessage($to, $interactive, $tenantId, $customHeaders, $language);
    }

    /**
     * Send a list message
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $bodyText The main message text
     * @param string $buttonText The button text to display
     * @param array $sections Array of list sections with items
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendListMessage(string $to, string $bodyText, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $bodyText],
            'action' => [
                'button' => $buttonText,
                'sections' => $sections
            ]
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }

        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->sendInteractiveMessage($to, $interactive, $tenantId, $customHeaders, $language);
    }

    /**
     * Upload media file to WhatsApp
     *
     * @param string $filePath The local file path to upload
     * @param string $type The media type (image, video, document, audio)
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data containing media ID
     * @throws WhatsappException When file not found or upload fails
     */
    public function uploadMedia(string $filePath, string $type, ?string $tenantId = null, array $customHeaders = []): array
    {
        $tenant = $this->tenantConfig($tenantId);

        if (!file_exists($filePath)) {
            throw new WhatsappException("File not found: {$filePath}");
        }

        $url = "{$this->config['base_uri']}/{$tenant['phone_number_id']}/media";

        $headers = $this->buildHeaders($tenant, $customHeaders);
        
        $response = Http::withHeaders($headers)
                        ->attach('file', file_get_contents($filePath), basename($filePath))
                        ->post($url, [
                            'messaging_product' => 'whatsapp',
                            'type' => $type
                        ]);

        if ($response->failed()) {
            throw new WhatsappException("Failed to upload media", $response->status(), $response->body());
        }

        return $response->json();
    }

    /**
     * Download media file from WhatsApp
     *
     * @param string $mediaId The media ID from WhatsApp
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return string The local file path where media was saved
     * @throws WhatsappException When media download fails
     */
    public function downloadMedia(string $mediaId, ?string $tenantId = null, array $customHeaders = []): string
    {
        $tenant = $this->tenantConfig($tenantId);

        $mediaUrl = "{$this->config['base_uri']}/{$mediaId}";
        $headers = $this->buildHeaders($tenant, $customHeaders);
        
        $response = Http::withHeaders($headers)->get($mediaUrl);

        if ($response->failed()) {
            throw new WhatsappException("Failed to fetch media URL");
        }

        $data = $response->json();
        $url = $data['url'];
        $mimeType = $data['mime_type'] ?? 'application/octet-stream';
        $fileExtension = $this->getFileExtensionFromMimeType($mimeType);

        $binary = Http::withHeaders($headers)->get($url)->body();

        $filename = "{$mediaId}.{$fileExtension}";
        $path = $this->config['media_storage'] . "/{$filename}";

        file_put_contents($path, $binary);

        return $path;
    }

    /**
     * Validate phone number format
     *
     * @param string $phoneNumber The phone number to validate
     * @return void
     * @throws WhatsappException When phone number format is invalid
     */
    protected function validatePhoneNumber(string $phoneNumber): void
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^\d]/', '', $phoneNumber);

        // Check if it's a valid phone number format
        if (strlen($cleaned) < 10 || strlen($cleaned) > 15) {
            throw new WhatsappException("Invalid phone number format: {$phoneNumber}");
        }
    }

    /**
     * Get file extension from MIME type
     *
     * @param string $mimeType The MIME type to convert
     * @return string The corresponding file extension
     */
    protected function getFileExtensionFromMimeType(string $mimeType): string
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            'audio/mpeg' => 'mp3',
            'audio/amr' => 'amr',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
        ];

        return $mimeToExt[$mimeType] ?? 'bin';
    }
}
