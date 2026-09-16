<?php

namespace Crenspire\Whatsapp\Builders;

use Crenspire\Whatsapp\Exceptions\WhatsappException;

/**
 * WhatsApp Template Builder
 *
 * This class provides a fluent interface for building WhatsApp template messages
 * with proper component structure and validation.
 *
 * @package Crenspire\Whatsapp\Builders
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class TemplateBuilder
{
    private string $templateName;
    private string $language;
    private array $components = [];

    /**
     * Create a new template builder instance
     *
     * @param string $templateName The template name
     * @param string $language The language code
     */
    public function __construct(string $templateName, string $language = 'en_US')
    {
        $this->templateName = $templateName;
        $this->language = $language;
    }

    /**
     * Add header component
     *
     * @param array $parameters The header parameters
     * @return self
     */
    public function header(array $parameters): self
    {
        $this->components[] = [
            'type' => 'header',
            'parameters' => self::parameters($parameters)
        ];

        return $this;
    }

    /**
     * Add body component
     *
     * @param array $parameters The body parameters
     * @return self
     */
    public function body(array $parameters): self
    {
        $this->components[] = [
            'type' => 'body',
            'parameters' => self::parameters($parameters)
        ];

        return $this;
    }

    /**
     * Add footer component
     *
     * @deprecated Template footers are static text and take no parameters when sending
     * @param array $parameters The footer parameters
     * @return self
     * @throws WhatsappException Always, since the API rejects footer parameters
     */
    public function footer(array $parameters): self
    {
        throw new WhatsappException("Template footers do not accept parameters");
    }

    /**
     * Add button component
     *
     * @param string $subType The button sub-type (quick_reply, url, ...)
     * @param array $parameters The button parameters
     * @param int $index The zero-based position of the button in the template
     * @return self
     */
    public function button(string $subType, array $parameters, int $index = 0): self
    {
        $this->components[] = [
            'type' => 'button',
            'sub_type' => $subType,
            'index' => (string) $index,
            'parameters' => self::parameters($parameters)
        ];

        return $this;
    }

    /**
     * Build the template message payload
     *
     * @return array The template message payload
     */
    public function build(): array
    {
        return [
            'type' => 'template',
            'template' => [
                'name' => $this->templateName,
                'language' => ['code' => $this->language],
                'components' => $this->components
            ]
        ];
    }

    /**
     * Format parameters for template components
     *
     * Plain values become text parameters. Arrays are passed through as full
     * parameter objects, e.g. ['type' => 'image', 'image' => ['id' => '...']],
     * with the type defaulting to text.
     *
     * @param array $parameters The raw parameters
     * @return array The formatted parameters
     */
    public static function parameters(array $parameters): array
    {
        return array_map(function ($param) {
            if (is_array($param)) {
                return isset($param['type']) ? $param : ['type' => 'text'] + $param;
            }

            return [
                'type' => 'text',
                'text' => (string) $param
            ];
        }, array_values($parameters));
    }

    /**
     * Create a new template builder instance
     *
     * @param string $templateName The template name
     * @param string $language The language code
     * @return self
     */
    public static function create(string $templateName, string $language = 'en_US'): self
    {
        return new self($templateName, $language);
    }
}
