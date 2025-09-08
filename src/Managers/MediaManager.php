<?php

namespace Crenspire\Whatsapp\Managers;

use Illuminate\Support\Facades\Http;
use Crenspire\Whatsapp\Exceptions\WhatsappException;

/**
 * WhatsApp Media Manager
 *
 * This class handles all media-related operations including upload,
 * download, info retrieval, and deletion.
 *
 * @package Crenspire\Whatsapp\Managers
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class MediaManager
{
    private string $baseUri;
    private string $phoneNumberId;
    private array $headers;
    private string $mediaStorage;

    /**
     * Create a new media manager instance
     *
     * @param string $baseUri The API base URI
     * @param string $phoneNumberId The phone number ID
     * @param array $headers The request headers
     * @param string $mediaStorage The media storage path
     */
    public function __construct(string $baseUri, string $phoneNumberId, array $headers, string $mediaStorage)
    {
        $this->baseUri = $baseUri;
        $this->phoneNumberId = $phoneNumberId;
        $this->headers = $headers;
        $this->mediaStorage = $mediaStorage;
    }

    /**
     * Upload media file to WhatsApp
     *
     * @param string $filePath The local file path to upload
     * @param string $type The media type
     * @return array The API response data containing media ID
     * @throws WhatsappException When file not found or upload fails
     */
    public function upload(string $filePath, string $type): array
    {
        if (!file_exists($filePath)) {
            throw new WhatsappException("File not found: {$filePath}");
        }

        $url = "{$this->baseUri}/{$this->phoneNumberId}/media";

        $response = Http::withHeaders($this->headers)
                        ->attach('file', file_get_contents($filePath), basename($filePath))
                        ->post($url, [
                            'messaging_product' => 'whatsapp',
                            'type' => $type
                        ]);

        if ($response->failed()) {
            throw new WhatsappException("Failed to upload media", $response->status(), $response->body());
        }

        return $response->json();
    }

    /**
     * Download media file from WhatsApp
     *
     * @param string $mediaId The media ID from WhatsApp
     * @return string The local file path where media was saved
     * @throws WhatsappException When media download fails
     */
    public function download(string $mediaId): string
    {
        $mediaInfo = $this->getInfo($mediaId);
        $url = $mediaInfo['url'];
        $mimeType = $mediaInfo['mime_type'] ?? 'application/octet-stream';
        $fileExtension = $this->getFileExtensionFromMimeType($mimeType);

        $binary = Http::withHeaders($this->headers)->get($url)->body();

        $filename = "{$mediaId}.{$fileExtension}";
        $path = $this->mediaStorage . "/{$filename}";

        file_put_contents($path, $binary);

        return $path;
    }

    /**
     * Get media information
     *
     * @param string $mediaId The media ID from WhatsApp
     * @return array The media information
     * @throws WhatsappException When media info fetch fails
     */
    public function getInfo(string $mediaId): array
    {
        $mediaUrl = "{$this->baseUri}/{$mediaId}";
        
        $response = Http::withHeaders($this->headers)->get($mediaUrl);

        if ($response->failed()) {
            throw new WhatsappException("Failed to fetch media information");
        }

        return $response->json();
    }

    /**
     * Delete media from WhatsApp
     *
     * @param string $mediaId The media ID from WhatsApp
     * @return bool True if deletion was successful
     * @throws WhatsappException When media deletion fails
     */
    public function delete(string $mediaId): bool
    {
        $mediaUrl = "{$this->baseUri}/{$mediaId}";
        
        $response = Http::withHeaders($this->headers)->delete($mediaUrl);

        if ($response->failed()) {
            throw new WhatsappException("Failed to delete media");
        }

        return $response->successful();
    }

    /**
     * Get file extension from MIME type
     *
     * @param string $mimeType The MIME type to convert
     * @return string The corresponding file extension
     */
    private function getFileExtensionFromMimeType(string $mimeType): string
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
}
