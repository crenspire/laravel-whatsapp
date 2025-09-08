<?php

namespace Crenspire\Whatsapp\Builders;

/**
 * WhatsApp Message Builder
 *
 * This class provides a fluent interface for building WhatsApp messages
 * with proper validation and structure.
 *
 * @package Crenspire\Whatsapp\Builders
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class MessageBuilder
{
    /**
     * Build a text message payload
     *
     * @param string $text The text content
     * @param bool $previewUrl Whether to show URL preview
     * @return array The message payload
     */
    public static function text(string $text, bool $previewUrl = false): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => $text,
                'preview_url' => $previewUrl
            ]
        ];
    }

    /**
     * Build a media message payload
     *
     * @param string $mediaId The media ID
     * @param string $type The media type
     * @param string|null $caption Optional caption
     * @return array The message payload
     */
    public static function media(string $mediaId, string $type, ?string $caption = null): array
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

        return $message;
    }

    /**
     * Build a template message payload
     *
     * @param string $templateName The template name
     * @param string $language The language code
     * @param array $components The template components
     * @return array The message payload
     */
    public static function template(string $templateName, string $language, array $components = []): array
    {
        return [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components
            ]
        ];
    }

    /**
     * Build a contact message payload
     *
     * @param array $contacts The contact data
     * @return array The message payload
     */
    public static function contacts(array $contacts): array
    {
        return [
            'type' => 'contacts',
            'contacts' => $contacts
        ];
    }

    /**
     * Build a location message payload
     *
     * @param float $latitude The latitude
     * @param float $longitude The longitude
     * @param string|null $name Optional location name
     * @param string|null $address Optional location address
     * @return array The message payload
     */
    public static function location(float $latitude, float $longitude, ?string $name = null, ?string $address = null): array
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

        return [
            'type' => 'location',
            'location' => $location
        ];
    }

    /**
     * Build a sticker message payload
     *
     * @param string $stickerId The sticker ID
     * @return array The message payload
     */
    public static function sticker(string $stickerId): array
    {
        return [
            'type' => 'sticker',
            'sticker' => [
                'id' => $stickerId
            ]
        ];
    }

    /**
     * Build a reaction message payload
     *
     * @param string $messageId The message ID to react to
     * @param string $emoji The emoji
     * @return array The message payload
     */
    public static function reaction(string $messageId, string $emoji): array
    {
        return [
            'type' => 'reaction',
            'reaction' => [
                'message_id' => $messageId,
                'emoji' => $emoji
            ]
        ];
    }

    /**
     * Build an interactive message payload
     *
     * @param array $interactive The interactive data
     * @return array The message payload
     */
    public static function interactive(array $interactive): array
    {
        return [
            'type' => 'interactive',
            'interactive' => $interactive
        ];
    }

    /**
     * Build a button interactive message
     *
     * @param string $bodyText The body text
     * @param array $buttons The button data
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @return array The interactive message payload
     */
    public static function buttonInteractive(string $bodyText, array $buttons, ?string $headerText = null, ?string $footerText = null): array
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

        return $interactive;
    }

    /**
     * Build a list interactive message
     *
     * @param string $bodyText The body text
     * @param string $buttonText The button text
     * @param array $sections The list sections
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @return array The interactive message payload
     */
    public static function listInteractive(string $bodyText, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null): array
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

        return $interactive;
    }

    /**
     * Build a flow interactive message
     *
     * @param string $flowToken The flow token
     * @param string $flowId The flow ID
     * @param string $flowCta The call-to-action text
     * @param string $flowAction The flow action
     * @param array $flowActionPayload The flow action payload
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @return array The interactive message payload
     */
    public static function flowInteractive(string $flowToken, string $flowId, string $flowCta, string $flowAction, array $flowActionPayload, ?string $headerText = null, ?string $footerText = null): array
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

        return $interactive;
    }

    /**
     * Build a single product interactive message
     *
     * @param string $catalogId The catalog ID
     * @param string $productRetailerId The product retailer ID
     * @param string|null $bodyText Optional body text
     * @param string|null $footerText Optional footer text
     * @return array The interactive message payload
     */
    public static function singleProductInteractive(string $catalogId, string $productRetailerId, ?string $bodyText = null, ?string $footerText = null): array
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

        return $interactive;
    }

    /**
     * Build a multi-product interactive message
     *
     * @param string $catalogId The catalog ID
     * @param string $buttonText The button text
     * @param array $sections The product sections
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @return array The interactive message payload
     */
    public static function multiProductInteractive(string $catalogId, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null): array
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

        return $interactive;
    }
}
