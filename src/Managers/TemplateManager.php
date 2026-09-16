<?php

namespace Crenspire\Whatsapp\Managers;

use Illuminate\Support\Facades\Http;
use Crenspire\Whatsapp\Exceptions\WhatsappException;

/**
 * WhatsApp Template Manager
 *
 * This class handles template-related operations including creation,
 * editing, deletion, retrieval, and status checking.
 *
 * Templates are submitted for review automatically when they are created
 * or edited; the API has no separate publish or unpublish operation.
 *
 * @package Crenspire\Whatsapp\Managers
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class TemplateManager
{
    public const CATEGORIES = ['MARKETING', 'UTILITY', 'AUTHENTICATION'];

    public const STATUSES = [
        'APPROVED', 'IN_APPEAL', 'PENDING', 'REJECTED', 'PENDING_DELETION',
        'DELETED', 'DISABLED', 'PAUSED', 'LIMIT_EXCEEDED',
    ];

    public const COMPONENT_TYPES = ['HEADER', 'BODY', 'FOOTER', 'BUTTONS'];

    public const HEADER_FORMATS = ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'];

    private string $baseUri;
    private string $businessAccountId;
    private array $headers;

    /**
     * Create a new template manager instance
     *
     * @param string $baseUri The API base URI
     * @param string $businessAccountId The WhatsApp Business Account ID
     * @param array $headers The request headers
     */
    public function __construct(string $baseUri, string $businessAccountId, array $headers)
    {
        $this->baseUri = $baseUri;
        $this->businessAccountId = $businessAccountId;
        $this->headers = $headers;
    }

    /**
     * Create a new message template and submit it for review
     *
     * @param string $name The template name
     * @param string $language The language code (e.g., 'en_US')
     * @param string $category The template category (MARKETING, UTILITY, AUTHENTICATION)
     * @param array $components The template components
     * @return array The API response data (id, status, category)
     * @throws WhatsappException When template creation fails
     */
    public function create(string $name, string $language, string $category, array $components): array
    {
        $this->validateCategory($category);
        $this->validateComponents($components);

        return $this->request('post', $this->templatesUrl(), [
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'components' => $components,
        ], 'create template', 60);
    }

    /**
     * Edit an existing message template identified by name and language
     *
     * The edited template is re-submitted for review automatically.
     *
     * @param string $name The template name
     * @param string $language The language code of the template version to edit
     * @param string $category The template category
     * @param array $components The updated template components (replaces all existing components)
     * @return array The API response data
     * @throws WhatsappException When the template is not found or the edit fails
     */
    public function update(string $name, string $language, string $category, array $components): array
    {
        $template = $this->getByName($name, $language);

        return $this->updateById($template['id'], $components, $category);
    }

    /**
     * Edit an existing message template by its ID
     *
     * @param string $templateId The template ID
     * @param array $components The updated template components (replaces all existing components)
     * @param string|null $category Optional new template category
     * @return array The API response data
     * @throws WhatsappException When the edit fails
     */
    public function updateById(string $templateId, array $components, ?string $category = null): array
    {
        $this->validateComponents($components);

        $payload = ['components' => $components];

        if ($category !== null) {
            $this->validateCategory($category);
            $payload['category'] = $category;
        }

        return $this->request('post', "{$this->baseUri}/{$templateId}", $payload, 'update template', 60);
    }

    /**
     * Delete a message template (all language versions)
     *
     * @param string $name The template name
     * @return bool True if deletion was successful
     * @throws WhatsappException When template deletion fails
     */
    public function delete(string $name): bool
    {
        $url = $this->templatesUrl() . '?' . http_build_query(['name' => $name]);

        $response = $this->request('delete', $url, [], 'delete template');

        return (bool) ($response['success'] ?? true);
    }

    /**
     * Get one page of message templates
     *
     * @param array $filters Optional query parameters (name, status, category, language, limit, after, ...)
     * @return array The API response data (data, paging)
     * @throws WhatsappException When template retrieval fails
     */
    public function getAll(array $filters = []): array
    {
        $url = $this->templatesUrl();

        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }

        return $this->request('get', $url, [], 'retrieve templates');
    }

    /**
     * Get a specific template by name, searching every page of results
     *
     * @param string $name The template name
     * @param string|null $language Optional language code to match a specific version
     * @return array The template data
     * @throws WhatsappException When the template is not found or retrieval fails
     */
    public function getByName(string $name, ?string $language = null): array
    {
        $filters = ['name' => $name];

        do {
            $page = $this->getAll($filters);

            // The name filter is not an exact match, so compare locally
            foreach ($page['data'] ?? [] as $template) {
                if (($template['name'] ?? null) === $name
                    && ($language === null || ($template['language'] ?? null) === $language)) {
                    return $template;
                }
            }

            $after = $page['paging']['cursors']['after'] ?? null;
            $filters['after'] = $after;
        } while ($after !== null && isset($page['paging']['next']));

        $label = $language === null ? $name : "{$name} ({$language})";

        throw new WhatsappException("Template '{$label}' not found");
    }

    /**
     * Get templates by status
     *
     * @param string $status The template status (see STATUSES)
     * @return array The filtered templates
     * @throws WhatsappException When template retrieval fails
     */
    public function getByStatus(string $status): array
    {
        $this->validateStatus($status);
        return $this->getAll(['status' => $status]);
    }

    /**
     * Get templates by category
     *
     * @param string $category The template category
     * @return array The filtered templates
     * @throws WhatsappException When template retrieval fails
     */
    public function getByCategory(string $category): array
    {
        $this->validateCategory($category);
        return $this->getAll(['category' => $category]);
    }

    /**
     * Get templates by language
     *
     * @param string $language The language code
     * @return array The filtered templates
     * @throws WhatsappException When template retrieval fails
     */
    public function getByLanguage(string $language): array
    {
        return $this->getAll(['language' => $language]);
    }

    /**
     * Get template status
     *
     * @param string $name The template name
     * @param string|null $language Optional language code to match a specific version
     * @return string The template status
     * @throws WhatsappException When template retrieval fails
     */
    public function getStatus(string $name, ?string $language = null): string
    {
        $template = $this->getByName($name, $language);
        return $template['status'] ?? 'UNKNOWN';
    }

    /**
     * Check if template is approved
     *
     * @param string $name The template name
     * @param string|null $language Optional language code to match a specific version
     * @return bool True if template is approved
     * @throws WhatsappException When template retrieval fails
     */
    public function isApproved(string $name, ?string $language = null): bool
    {
        return $this->getStatus($name, $language) === 'APPROVED';
    }

    /**
     * Check if template is pending review
     *
     * @param string $name The template name
     * @param string|null $language Optional language code to match a specific version
     * @return bool True if template is pending
     * @throws WhatsappException When template retrieval fails
     */
    public function isPending(string $name, ?string $language = null): bool
    {
        return $this->getStatus($name, $language) === 'PENDING';
    }

    /**
     * Get the message templates endpoint for the business account
     *
     * @return string The endpoint URL
     */
    private function templatesUrl(): string
    {
        return "{$this->baseUri}/{$this->businessAccountId}/message_templates";
    }

    /**
     * Send a request to the Graph API and decode the response
     *
     * @param string $method The HTTP method (get, post, delete)
     * @param string $url The request URL
     * @param array $payload The JSON payload for POST requests
     * @param string $action Description of the action for error messages
     * @param int $timeout The request timeout in seconds
     * @return array The API response data
     * @throws WhatsappException When the request fails
     */
    private function request(string $method, string $url, array $payload, string $action, int $timeout = 30): array
    {
        $client = Http::withHeaders($this->headers)->timeout($timeout);

        $response = $method === 'post'
            ? $client->post($url, $payload)
            : $client->{$method}($url);

        if ($response->failed()) {
            $errorData = $response->json();
            throw new WhatsappException(
                "Failed to {$action}: " . ($errorData['error']['message'] ?? 'Unknown error'),
                $response->status(),
                $response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Validate template category
     *
     * @param string $category The category to validate
     * @return void
     * @throws WhatsappException When category is invalid
     */
    private function validateCategory(string $category): void
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new WhatsappException(
                "Invalid template category: {$category}. Valid categories are: " . implode(', ', self::CATEGORIES)
            );
        }
    }

    /**
     * Validate template status
     *
     * @param string $status The status to validate
     * @return void
     * @throws WhatsappException When status is invalid
     */
    private function validateStatus(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new WhatsappException(
                "Invalid template status: {$status}. Valid statuses are: " . implode(', ', self::STATUSES)
            );
        }
    }

    /**
     * Validate template components
     *
     * @param array $components The components to validate
     * @return void
     * @throws WhatsappException When components are invalid
     */
    private function validateComponents(array $components): void
    {
        if (empty($components)) {
            throw new WhatsappException("Template components cannot be empty");
        }

        foreach ($components as $component) {
            if (!isset($component['type']) || !in_array($component['type'], self::COMPONENT_TYPES, true)) {
                throw new WhatsappException(
                    "Invalid component type. Valid types are: " . implode(', ', self::COMPONENT_TYPES)
                );
            }

            if (isset($component['format']) && !in_array($component['format'], self::HEADER_FORMATS, true)) {
                throw new WhatsappException(
                    "Invalid component format. Valid formats are: " . implode(', ', self::HEADER_FORMATS)
                );
            }
        }

        if (!in_array('BODY', array_column($components, 'type'), true)) {
            throw new WhatsappException("Template components must include a BODY component");
        }
    }
}
