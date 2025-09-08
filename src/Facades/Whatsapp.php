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
 * @method static array sendTemplateMessageWithComponents(string $to, string $templateName, array $bodyParameters = [], array $headerParameters = [], array $footerParameters = [], ?string $language = null, ?string $tenantId = null, array $customHeaders = [])
 * @method static array sendInteractiveMessage(string $to, array $interactive, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendButtonMessage(string $to, string $bodyText, array $buttons, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendListMessage(string $to, string $bodyText, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendContactMessage(string $to, array $contacts, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendLocationMessage(string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendStickerMessage(string $to, string $stickerId, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendReactionMessage(string $to, string $messageId, string $emoji, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendFlowMessage(string $to, string $flowToken, string $flowId, string $flowCta, string $flowAction, array $flowActionPayload, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendSingleProductMessage(string $to, string $catalogId, string $productRetailerId, ?string $bodyText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendMultiProductMessage(string $to, string $catalogId, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array uploadMedia(string $filePath, string $type, ?string $tenantId = null, array $customHeaders = [])
 * @method static string downloadMedia(string $mediaId, ?string $tenantId = null, array $customHeaders = [])
 * @method static array getMediaInfo(string $mediaId, ?string $tenantId = null, array $customHeaders = [])
 * @method static bool deleteMedia(string $mediaId, ?string $tenantId = null, array $customHeaders = [])
 * @method static array getBusinessProfile(?string $tenantId = null, array $customHeaders = [])
 * @method static array updateBusinessProfile(array $profileData, ?string $tenantId = null, array $customHeaders = [])
 * @method static array markMessageAsRead(string $messageId, ?string $tenantId = null, array $customHeaders = [])
 * @method static \Crenspire\Whatsapp\Builders\TemplateBuilder template(string $templateName, ?string $language = null, ?string $tenantId = null)
 * @method static \Crenspire\Whatsapp\Builders\MessageBuilder message()
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
