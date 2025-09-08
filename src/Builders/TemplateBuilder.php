<?php

namespace Crenspire\Whatsapp\Builders;

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
            'parameters' => $this->formatParameters($parameters)
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
            'parameters' => $this->formatParameters($parameters)
        ];

        return $this;
    }

    /**
     * Add footer component
     *
     * @param array $parameters The footer parameters
     * @return self
     */
    public function footer(array $parameters): self
    {
        $this->components[] = [
            'type' => 'footer',
            'parameters' => $this->formatParameters($parameters)
        ];

        return $this;
    }

    /**
     * Add button component
     *
     * @param string $subType The button sub-type
     * @param array $parameters The button parameters
     * @return self
     */
    public function button(string $subType, array $parameters): self
    {
        $this->components[] = [
            'type' => 'button',
            'sub_type' => $subType,
            'parameters' => $this->formatParameters($parameters)
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
     * @param array $parameters The raw parameters
     * @return array The formatted parameters
     */
    private function formatParameters(array $parameters): array
    {
        return array_map(function($param) {
            if (is_array($param)) {
                return [
                    'type' => $param['type'] ?? 'text',
                    'text' => $param['text'] ?? $param
                ];
            }

            return [
                'type' => 'text',
                'text' => $param
            ];
        }, $parameters);
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
