<?php

namespace Crenspire\Whatsapp\Managers;

use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\Http\GraphClient;

/**
 * WhatsApp Business Profile Manager
 *
 * This class handles business profile operations including
 * retrieval and updates.
 *
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 */
class BusinessProfileManager
{
    public const FIELDS = [
        'about', 'address', 'description', 'email', 'profile_picture_url', 'websites', 'vertical',
    ];

    private string $baseUri;

    private string $phoneNumberId;

    private GraphClient $client;

    /**
     * Create a new business profile manager instance
     *
     * @param  string  $baseUri  The API base URI
     * @param  string  $phoneNumberId  The phone number ID
     * @param  GraphClient  $client  The client used to call the API
     */
    public function __construct(string $baseUri, string $phoneNumberId, GraphClient $client)
    {
        $this->baseUri = $baseUri;
        $this->phoneNumberId = $phoneNumberId;
        $this->client = $client;
    }

    /**
     * Get business profile information
     *
     * @return array The business profile information
     *
     * @throws WhatsappException When profile fetch fails
     */
    public function get(): array
    {
        return $this->client->get(
            "{$this->baseUri}/{$this->phoneNumberId}/whatsapp_business_profile",
            ['fields' => implode(',', self::FIELDS)],
            'fetch business profile'
        );
    }

    /**
     * Update business profile information
     *
     * @param  array  $profileData  The profile data to update
     * @return array The API response data
     *
     * @throws WhatsappException When profile update fails
     */
    public function update(array $profileData): array
    {
        return $this->client->post(
            "{$this->baseUri}/{$this->phoneNumberId}/whatsapp_business_profile",
            array_merge(['messaging_product' => 'whatsapp'], $profileData),
            'update business profile',
            idempotent: true
        );
    }

    /**
     * Update business profile about text
     *
     * @param  string  $about  The about text
     * @return array The API response data
     *
     * @throws WhatsappException When profile update fails
     */
    public function updateAbout(string $about): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'about' => $about,
        ]);
    }

    /**
     * Update business profile email
     *
     * @param  string  $email  The email address
     * @return array The API response data
     *
     * @throws WhatsappException When profile update fails
     */
    public function updateEmail(string $email): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'email' => $email,
        ]);
    }

    /**
     * Update business profile website
     *
     * @param  string  $website  The website URL
     * @return array The API response data
     *
     * @throws WhatsappException When profile update fails
     */
    public function updateWebsite(string $website): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'websites' => [$website],
        ]);
    }

    /**
     * Update business profile address
     *
     * @param  string  $address  The address
     * @return array The API response data
     *
     * @throws WhatsappException When profile update fails
     */
    public function updateAddress(string $address): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'address' => $address,
        ]);
    }

    /**
     * Update business profile description
     *
     * @param  string  $description  The description
     * @return array The API response data
     *
     * @throws WhatsappException When profile update fails
     */
    public function updateDescription(string $description): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'description' => $description,
        ]);
    }

    /**
     * Update business profile vertical
     *
     * @param  string  $vertical  The business vertical
     * @return array The API response data
     *
     * @throws WhatsappException When profile update fails
     */
    public function updateVertical(string $vertical): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'vertical' => $vertical,
        ]);
    }
}
