<?php

namespace Crenspire\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\Events\MessageSent;
use Crenspire\Whatsapp\Events\MessageFailed;
use Crenspire\Whatsapp\Builders\MessageBuilder;
use Crenspire\Whatsapp\Builders\TemplateBuilder;
use Crenspire\Whatsapp\Managers\MediaManager;
use Crenspire\Whatsapp\Managers\BusinessProfileManager;

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
     * Media manager instance
     *
     * @var MediaManager|null
     */
    protected ?MediaManager $mediaManager = null;

    /**
     * Business profile manager instance
     *
     * @var BusinessProfileManager|null
     */
    protected ?BusinessProfileManager $businessProfileManager = null;

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

    public function debugEnabled(): bool
    {
        return $this->config['debug'] ?? false;
    }

    public function createDebugLog(string $message, array $context = [], string $level = 'info'): void
    {
        if ($this->debugEnabled()) {
            switch ($level) {
                case 'info':
                    Log::info($message, $context);
                    break;
                case 'warning':
                    Log::warning($message, $context);
                    break;
                case 'error':
                    Log::error($message, $context);
                    break;
            }
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

        $this->createDebugLog('Sending WhatsApp message', [
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
            $this->createDebugLog('WhatsApp message failed', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $errorData
            ], 'error');

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
     * @param bool $previewUrl Whether to show URL preview
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendTextMessage(string $to, string $text, bool $previewUrl = false, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        $message = MessageBuilder::text($text, $previewUrl);
        return $this->sendMessage($to, $message, $tenantId, $customHeaders, $language);
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
        $message = MessageBuilder::media($mediaId, $type, $caption);
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
     * Send a template message with header and footer components
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $templateName The name of the approved template
     * @param array $bodyParameters Optional body parameters to fill template placeholders
     * @param array $headerParameters Optional header parameters (for media templates)
     * @param array $footerParameters Optional footer parameters
     * @param string|null $language The language code
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendTemplateMessageWithComponents(string $to, string $templateName, array $bodyParameters = [], array $headerParameters = [], array $footerParameters = [], ?string $language = null, ?string $tenantId = null, array $customHeaders = []): array
    {
        $tenant = $this->tenantConfig($tenantId);
        $language = $this->getLanguage($tenant, $language);
        
        $components = [];

        // Add header component if parameters provided
        if (!empty($headerParameters)) {
            $components[] = [
                'type' => 'header',
                'parameters' => array_map(fn($param) => [
                    'type' => $param['type'] ?? 'text',
                    'text' => $param['text'] ?? $param
                ], $headerParameters)
            ];
        }

        // Add body component if parameters provided
        if (!empty($bodyParameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn($param) => [
                    'type' => $param['type'] ?? 'text',
                    'text' => $param['text'] ?? $param
                ], $bodyParameters)
            ];
        }

        // Add footer component if parameters provided
        if (!empty($footerParameters)) {
            $components[] = [
                'type' => 'footer',
                'parameters' => array_map(fn($param) => [
                    'type' => $param['type'] ?? 'text',
                    'text' => $param['text'] ?? $param
                ], $footerParameters)
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
     * Send a contact message
     *
     * @param string $to The recipient phone number (with country code)
     * @param array $contacts Array of contact information
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendContactMessage(string $to, array $contacts, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        return $this->sendMessage($to, [
            'type' => 'contacts',
            'contacts' => $contacts
        ], $tenantId, $customHeaders, $language);
    }

    /**
     * Send a location message
     *
     * @param string $to The recipient phone number (with country code)
     * @param float $latitude The latitude coordinate
     * @param float $longitude The longitude coordinate
     * @param string|null $name Optional location name
     * @param string|null $address Optional location address
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendLocationMessage(string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        $location = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];

        if ($name) {
            $location['name'] = $name;
        }

        if ($address) {
            $location['address'] = $address;
        }

        return $this->sendMessage($to, [
            'type' => 'location',
            'location' => $location
        ], $tenantId, $customHeaders, $language);
    }

    /**
     * Send a sticker message
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $stickerId The sticker ID from WhatsApp (uploaded via uploadMedia method)
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendStickerMessage(string $to, string $stickerId, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        return $this->sendMessage($to, [
            'type' => 'sticker',
            'sticker' => [
                'id' => $stickerId
            ]
        ], $tenantId, $customHeaders, $language);
    }

    /**
     * Send a reaction message
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $messageId The message ID to react to
     * @param string $emoji The emoji for the reaction
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendReactionMessage(string $to, string $messageId, string $emoji, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        return $this->sendMessage($to, [
            'type' => 'reaction',
            'reaction' => [
                'message_id' => $messageId,
                'emoji' => $emoji
            ]
        ], $tenantId, $customHeaders, $language);
    }

    /**
     * Send a flow message
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $flowToken The flow token
     * @param string $flowId The flow ID
     * @param string $flowCta The call-to-action text
     * @param string $flowAction The flow action (navigate, data_exchange)
     * @param array $flowActionPayload The flow action payload
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendFlowMessage(string $to, string $flowToken, string $flowId, string $flowCta, string $flowAction, array $flowActionPayload, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        $interactive = [
            'type' => 'flow',
            'body' => ['text' => $flowCta],
            'action' => [
                'name' => 'flow',
                'parameters' => [
                    'flow_token' => $flowToken,
                    'flow_id' => $flowId,
                    'flow_cta' => $flowCta,
                    'flow_action' => $flowAction,
                    'flow_action_payload' => $flowActionPayload
                ]
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
     * Send a single product message
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $catalogId The catalog ID
     * @param string $productRetailerId The product retailer ID
     * @param string|null $bodyText Optional body text
     * @param string|null $footerText Optional footer text
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendSingleProductMessage(string $to, string $catalogId, string $productRetailerId, ?string $bodyText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        $interactive = [
            'type' => 'product',
            'action' => [
                'catalog_id' => $catalogId,
                'product_retailer_id' => $productRetailerId
            ]
        ];

        if ($bodyText) {
            $interactive['body'] = ['text' => $bodyText];
        }

        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->sendInteractiveMessage($to, $interactive, $tenantId, $customHeaders, $language);
    }

    /**
     * Send a multi-product message
     *
     * @param string $to The recipient phone number (with country code)
     * @param string $catalogId The catalog ID
     * @param string $buttonText The button text
     * @param array $sections Array of product sections
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When rate limit is exceeded or API call fails
     */
    public function sendMultiProductMessage(string $to, string $catalogId, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null): array
    {
        $interactive = [
            'type' => 'product_list',
            'body' => ['text' => $buttonText],
            'action' => [
                'catalog_id' => $catalogId,
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
        $mediaManager = $this->getMediaManager($tenantId);
        return $mediaManager->upload($filePath, $type);
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
        $mediaManager = $this->getMediaManager($tenantId);
        return $mediaManager->download($mediaId);
    }

    /**
     * Get media information
     *
     * @param string $mediaId The media ID from WhatsApp
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The media information
     * @throws WhatsappException When media info fetch fails
     */
    public function getMediaInfo(string $mediaId, ?string $tenantId = null, array $customHeaders = []): array
    {
        $mediaManager = $this->getMediaManager($tenantId);
        return $mediaManager->getInfo($mediaId);
    }

    /**
     * Delete media from WhatsApp
     *
     * @param string $mediaId The media ID from WhatsApp
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return bool True if deletion was successful
     * @throws WhatsappException When media deletion fails
     */
    public function deleteMedia(string $mediaId, ?string $tenantId = null, array $customHeaders = []): bool
    {
        $mediaManager = $this->getMediaManager($tenantId);
        return $mediaManager->delete($mediaId);
    }

    /**
     * Get business profile information
     *
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The business profile information
     * @throws WhatsappException When profile fetch fails
     */
    public function getBusinessProfile(?string $tenantId = null, array $customHeaders = []): array
    {
        $businessProfileManager = $this->getBusinessProfileManager($tenantId);
        return $businessProfileManager->get();
    }

    /**
     * Update business profile information
     *
     * @param array $profileData The profile data to update
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When profile update fails
     */
    public function updateBusinessProfile(array $profileData, ?string $tenantId = null, array $customHeaders = []): array
    {
        $businessProfileManager = $this->getBusinessProfileManager($tenantId);
        return $businessProfileManager->update($profileData);
    }

    /**
     * Mark message as read
     *
     * @param string $messageId The message ID to mark as read
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return array The API response data
     * @throws WhatsappException When marking as read fails
     */
    public function markMessageAsRead(string $messageId, ?string $tenantId = null, array $customHeaders = []): array
    {
        $tenant = $this->tenantConfig($tenantId);

        $url = "{$this->config['base_uri']}/{$tenant['phone_number_id']}/messages";
        $headers = $this->buildHeaders($tenant, $customHeaders);

        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];

        $response = Http::withHeaders($headers)
                        ->timeout(30)
                        ->post($url, $payload);

        if ($response->failed()) {
            $errorData = $response->json();
            throw new WhatsappException(
                "Failed to mark message as read: " . ($errorData['error']['message'] ?? 'Unknown error'),
                $response->status(),
                $response->body()
            );
        }

        return $response->json();
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

    /**
     * Get media manager instance
     *
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return MediaManager The media manager instance
     */
    protected function getMediaManager(?string $tenantId = null): MediaManager
    {
        if ($this->mediaManager === null) {
            $tenant = $this->tenantConfig($tenantId);
            $headers = $this->buildHeaders($tenant);
            
            $this->mediaManager = new MediaManager(
                $this->config['base_uri'],
                $tenant['phone_number_id'],
                $headers,
                $this->config['media_storage']
            );
        }

        return $this->mediaManager;
    }

    /**
     * Get business profile manager instance
     *
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return BusinessProfileManager The business profile manager instance
     */
    protected function getBusinessProfileManager(?string $tenantId = null): BusinessProfileManager
    {
        if ($this->businessProfileManager === null) {
            $tenant = $this->tenantConfig($tenantId);
            $headers = $this->buildHeaders($tenant);
            
            $this->businessProfileManager = new BusinessProfileManager(
                $this->config['base_uri'],
                $tenant['phone_number_id'],
                $headers
            );
        }

        return $this->businessProfileManager;
    }

    /**
     * Create a template builder instance
     *
     * @param string $templateName The template name
     * @param string|null $language The language code
     * @param string|null $tenantId Optional tenant ID for multi-tenant setups
     * @return TemplateBuilder The template builder instance
     */
    public function template(string $templateName, ?string $language = null, ?string $tenantId = null): TemplateBuilder
    {
        $tenant = $this->tenantConfig($tenantId);
        $language = $this->getLanguage($tenant, $language);
        
        return TemplateBuilder::create($templateName, $language);
    }

    /**
     * Create a message builder instance
     *
     * @return MessageBuilder The message builder instance
     */
    public function message(): MessageBuilder
    {
        return new MessageBuilder();
    }
}
