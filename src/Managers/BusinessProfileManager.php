<?php

namespace Crenspire\Whatsapp\Managers;

use Illuminate\Support\Facades\Http;
use Crenspire\Whatsapp\Exceptions\WhatsappException;

/**
 * WhatsApp Business Profile Manager
 *
 * This class handles business profile operations including
 * retrieval and updates.
 *
 * @package Crenspire\Whatsapp\Managers
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class BusinessProfileManager
{
    private string $baseUri;
    private string $phoneNumberId;
    private array $headers;

    /**
     * Create a new business profile manager instance
     *
     * @param string $baseUri The API base URI
     * @param string $phoneNumberId The phone number ID
     * @param array $headers The request headers
     */
    public function __construct(string $baseUri, string $phoneNumberId, array $headers)
    {
        $this->baseUri = $baseUri;
        $this->phoneNumberId = $phoneNumberId;
        $this->headers = $headers;
    }

    /**
     * Get business profile information
     *
     * @return array The business profile information
     * @throws WhatsappException When profile fetch fails
     */
    public function get(): array
    {
        $profileUrl = "{$this->baseUri}/{$this->phoneNumberId}/whatsapp_business_profile";
        
        $response = Http::withHeaders($this->headers)->get($profileUrl);

        if ($response->failed()) {
            throw new WhatsappException("Failed to fetch business profile");
        }

        return $response->json();
    }

    /**
     * Update business profile information
     *
     * @param array $profileData The profile data to update
     * @return array The API response data
     * @throws WhatsappException When profile update fails
     */
    public function update(array $profileData): array
    {
        $profileUrl = "{$this->baseUri}/{$this->phoneNumberId}/whatsapp_business_profile";
        
        $response = Http::withHeaders($this->headers)
                        ->timeout(30)
                        ->post($profileUrl, $profileData);

        if ($response->failed()) {
            $errorData = $response->json();
            throw new WhatsappException(
                "Failed to update business profile: " . ($errorData['error']['message'] ?? 'Unknown error'),
                $response->status(),
                $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Update business profile about text
     *
     * @param string $about The about text
     * @return array The API response data
     * @throws WhatsappException When profile update fails
     */
    public function updateAbout(string $about): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'about' => $about
        ]);
    }

    /**
     * Update business profile email
     *
     * @param string $email The email address
     * @return array The API response data
     * @throws WhatsappException When profile update fails
     */
    public function updateEmail(string $email): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'email' => $email
        ]);
    }

    /**
     * Update business profile website
     *
     * @param string $website The website URL
     * @return array The API response data
     * @throws WhatsappException When profile update fails
     */
    public function updateWebsite(string $website): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'website' => [$website]
        ]);
    }

    /**
     * Update business profile address
     *
     * @param string $address The address
     * @return array The API response data
     * @throws WhatsappException When profile update fails
     */
    public function updateAddress(string $address): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'address' => $address
        ]);
    }

    /**
     * Update business profile description
     *
     * @param string $description The description
     * @return array The API response data
     * @throws WhatsappException When profile update fails
     */
    public function updateDescription(string $description): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'description' => $description
        ]);
    }

    /**
     * Update business profile vertical
     *
     * @param string $vertical The business vertical
     * @return array The API response data
     * @throws WhatsappException When profile update fails
     */
    public function updateVertical(string $vertical): array
    {
        return $this->update([
            'messaging_product' => 'whatsapp',
            'vertical' => $vertical
        ]);
    }
}
