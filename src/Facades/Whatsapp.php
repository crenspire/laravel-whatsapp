<?php

namespace Crenspire\Whatsapp\Facades;

use Crenspire\Whatsapp\Testing\WhatsappFake;
use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Support\Facades\Facade;

/**
 * Send and receive WhatsApp messages through Meta's Cloud API
 *
 * @method static array sendMessage(string $to, array $message, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendTextMessage(string $to, string $text, bool $previewUrl = false, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendMediaMessage(string $to, string $mediaId, string $type, ?string $caption = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendTemplateMessage(string $to, string $templateName, array $parameters = [], ?string $language = null, ?string $tenantId = null, array $customHeaders = [], ?string $replyTo = null)
 * @method static array sendTemplateMessageWithComponents(string $to, string $templateName, array $bodyParameters = [], array $headerParameters = [], array $footerParameters = [], ?string $language = null, ?string $tenantId = null, array $customHeaders = [], ?string $replyTo = null)
 * @method static array sendInteractiveMessage(string $to, array $interactive, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendButtonMessage(string $to, string $bodyText, array $buttons, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendListMessage(string $to, string $bodyText, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendContactMessage(string $to, array $contacts, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendLocationMessage(string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendStickerMessage(string $to, string $stickerId, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendReactionMessage(string $to, string $messageId, string $emoji, ?string $tenantId = null, array $customHeaders = [], ?string $language = null)
 * @method static array sendFlowMessage(string $to, string $flowToken, string $flowId, string $flowCta, string $flowAction, array $flowActionPayload, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendSingleProductMessage(string $to, string $catalogId, string $productRetailerId, ?string $bodyText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array sendMultiProductMessage(string $to, string $catalogId, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null)
 * @method static array uploadMedia(string $filePath, string $type, ?string $tenantId = null, array $customHeaders = [])
 * @method static string downloadMedia(string $mediaId, ?string $tenantId = null, array $customHeaders = [])
 * @method static array getMediaInfo(string $mediaId, ?string $tenantId = null, array $customHeaders = [])
 * @method static bool deleteMedia(string $mediaId, ?string $tenantId = null, array $customHeaders = [])
 * @method static array getBusinessProfile(?string $tenantId = null, array $customHeaders = [])
 * @method static array updateBusinessProfile(array $profileData, ?string $tenantId = null, array $customHeaders = [])
 * @method static array getPhoneNumber(?string $tenantId = null, array $fields = ['display_phone_number', 'verified_name', 'quality_rating'])
 * @method static string uploadTemplateMedia(string $filePath, ?string $tenantId = null)
 * @method static array getWebhookSubscriptions(?string $tenantId = null)
 * @method static array markMessageAsRead(string $messageId, ?string $tenantId = null, array $customHeaders = [])
 * @method static array showTypingIndicator(string $messageId, ?string $tenantId = null, array $customHeaders = [])
 * @method static \Crenspire\Whatsapp\Builders\TemplateBuilder template(string $templateName, ?string $language = null, ?string $tenantId = null)
 * @method static \Crenspire\Whatsapp\Builders\MessageBuilder message()
 * @method static array createTemplate(string $name, string $language, string $category, array $components, ?string $tenantId = null)
 * @method static array updateTemplate(string $name, string $language, string $category, array $components, ?string $tenantId = null)
 * @method static bool deleteTemplate(string $name, ?string $tenantId = null)
 * @method static array getTemplates(array $filters = [], ?string $tenantId = null)
 * @method static array getTemplate(string $name, ?string $tenantId = null)
 * @method static array getTemplatesByStatus(string $status, ?string $tenantId = null)
 * @method static array getTemplatesByCategory(string $category, ?string $tenantId = null)
 * @method static array getTemplatesByLanguage(string $language, ?string $tenantId = null)
 * @method static string getTemplateStatus(string $name, ?string $tenantId = null)
 * @method static bool isTemplateApproved(string $name, ?string $tenantId = null)
 * @method static bool isTemplatePending(string $name, ?string $tenantId = null)
 * @method static \Crenspire\Whatsapp\Testing\WhatsappFake failSending(\Crenspire\Whatsapp\Exceptions\WhatsappException|\Closure|null $failure = null)
 * @method static \Crenspire\Whatsapp\Testing\WhatsappFake withTemplates(array $templates)
 * @method static \Illuminate\Support\Collection sent(?callable $callback = null)
 * @method static void assertSent(?callable $callback = null)
 * @method static void assertSentTo(string $to, ?callable $callback = null)
 * @method static void assertSentText(string $to, ?string $text = null)
 * @method static void assertSentTemplate(string $to, string $template, ?string $language = null)
 * @method static void assertNotSent(callable $callback)
 * @method static void assertNotSentTo(string $to)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $count)
 * @method static void assertMarkedAsRead(string $messageId)
 * @method static void assertTypingIndicatorShown(string $messageId)
 * @method static void assertMediaUploaded(?callable $callback = null)
 * @method static void assertTemplateCreated(string $name, ?callable $callback = null)
 *
 * @see WhatsappService
 * @see WhatsappFake
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
        return WhatsappService::class;
    }

    /**
     * Replace the service with a fake that records messages instead of sending them
     *
     * @param  array  $config  Config overrides for the fake, e.g. tenants
     */
    public static function fake(array $config = []): WhatsappFake
    {
        $fake = new WhatsappFake(array_replace(
            static::$app['config']->get('whatsapp', []),
            ['media_storage' => sys_get_temp_dir().'/whatsapp-fake-media'],
            $config
        ));

        static::swap($fake);

        return $fake;
    }
}
