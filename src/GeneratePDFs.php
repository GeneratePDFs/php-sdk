<?php

declare(strict_types=1);

namespace GeneratePDFs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

class GeneratePDFs
{
    private const BASE_URL = 'https://api.generatepdfs.com';

    private Client $client;

    private string $apiToken;

    private function __construct(string $apiToken)
    {
        $this->apiToken = $apiToken;
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 30.0,
        ]);
    }

    /**
     * Create a new GeneratePDFs instance with the provided API token.
     *
     * @param string $apiToken The API token for authentication
     * @return self
     */
    public static function connect(string $apiToken): self
    {
        return new self($apiToken);
    }

    /**
     * Generate a PDF from HTML file(s) with optional CSS and images.
     *
     * @param string $htmlPath Path to the HTML file
     * @param string|null $cssPath Optional path to the CSS file
     * @param array<int, array{name: string, path: string, mime_type?: string}> $images Optional array of image files
     * @return Pdf PDF object containing PDF information
     * @throws InvalidArgumentException If files are invalid
     * @throws GuzzleException If the HTTP request fails
     */
    public function generateFromHtml(
        string $htmlPath,
        ?string $cssPath = null,
        array $images = []
    ): Pdf {
        if (! file_exists($htmlPath) || ! is_readable($htmlPath)) {
            throw new InvalidArgumentException("HTML file not found or not readable: {$htmlPath}");
        }

        $htmlContent = base64_encode(file_get_contents($htmlPath));

        $data = [
            'html' => $htmlContent,
        ];

        if ($cssPath !== null) {
            if (! file_exists($cssPath) || ! is_readable($cssPath)) {
                throw new InvalidArgumentException("CSS file not found or not readable: {$cssPath}");
            }

            $data['css'] = base64_encode(file_get_contents($cssPath));
        }

        if (! empty($images)) {
            $data['images'] = $this->processImages($images);
        }

        $response = $this->makeRequest('/pdfs/generate', $data);

        if (! isset($response['pdf'])) {
            throw new InvalidArgumentException('Invalid API response: missing pdf data');
        }

        return Pdf::fromArray($response['pdf'], $this);
    }

    /**
     * Generate a PDF from a URL.
     *
     * @param string $url The URL to convert to PDF
     * @return Pdf PDF object containing PDF information
     * @throws InvalidArgumentException If URL is invalid
     * @throws GuzzleException If the HTTP request fails
     */
    public function generateFromUrl(string $url): Pdf
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL: {$url}");
        }

        $data = [
            'url' => $url,
        ];

        $response = $this->makeRequest('/pdfs/generate', $data);

        if (! isset($response['pdf'])) {
            throw new InvalidArgumentException('Invalid API response: missing pdf data');
        }

        return Pdf::fromArray($response['pdf'], $this);
    }

    /**
     * Process image files and return formatted array for API.
     *
     * @param array<int, array{name: string, path: string, mime_type?: string}> $images
     * @return array<int, array{name: string, content: string, mime_type: string}>
     */
    private function processImages(array $images): array
    {
        $processed = [];

        foreach ($images as $image) {
            if (! isset($image['path']) || ! isset($image['name'])) {
                continue;
            }

            $path = $image['path'];
            $name = $image['name'];

            if (! file_exists($path) || ! is_readable($path)) {
                continue;
            }

            $content = base64_encode(file_get_contents($path));

            // Detect mime type if not provided
            $mimeType = $image['mime_type'] ?? $this->detectMimeType($path);

            $processed[] = [
                'name' => $name,
                'content' => $content,
                'mime_type' => $mimeType,
            ];
        }

        return $processed;
    }

    /**
     * Detect MIME type of a file.
     *
     * @param string $filePath Path to the file
     * @return string MIME type
     */
    private function detectMimeType(string $filePath): string
    {
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath);
            if ($mimeType !== false) {
                return $mimeType;
            }
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if ($mimeType !== false) {
                    return $mimeType;
                }
            }
        }

        // Fallback to extension-based detection
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Download a PDF from the API.
     *
     * @param string $downloadUrl The download URL for the PDF
     * @return string PDF binary content
     * @throws GuzzleException If the HTTP request fails
     */
    public function downloadPdf(string $downloadUrl): string
    {
        $response = $this->client->get($downloadUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
            ],
        ]);

        return (string) $response->getBody();
    }

    /**
     * Make an HTTP request to the API.
     *
     * @param string $endpoint API endpoint
     * @param array<string, mixed> $data Request data
     * @return array<string, mixed> Decoded JSON response
     * @throws GuzzleException If the HTTP request fails
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        $response = $this->client->post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $data,
        ]);

        $body = (string) $response->getBody();

        return json_decode($body, true) ?? [];
    }
}
