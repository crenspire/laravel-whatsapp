<?php

namespace Crenspire\Whatsapp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * WhatsApp Facade
 * 
 * This facade provides a convenient static interface to the WhatsApp service,
 * allowing easy access to all WhatsApp functionality throughout the application.
 * 
 * @package Crenspire\Whatsapp\Facades
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 * 
 * @method static array sendMessage(string $to, array $message, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendTextMessage(string $to, string $text, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendMediaMessage(string $to, string $mediaId, string $type, ?string $caption = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendTemplateMessage(string $to, string $templateName, array $parameters = [], ?string $language = null, ?string $tenantId = null, array $customHeaders = [])
 * @method static array sendInteractiveMessage(string $to, array $interactive, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendButtonMessage(string $to, string $bodyText, array $buttons, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendListMessage(string $to, string $bodyText, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array uploadMedia(string $filePath, string $type, ?string $tenantId = null, array $customHeaders = [])
 * @method static string downloadMedia(string $mediaId, ?string $tenantId = null, array $customHeaders = [])
 */
class Whatsapp extends Facade
{
    /**
     * Get the registered name of the component
     * 
     * @return string The service class name
     */
    protected static function getFacadeAccessor(): string
    {
        return \Crenspire\Whatsapp\WhatsappService::class;
    }
}
