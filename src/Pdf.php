<?php

declare(strict_types=1);

namespace GeneratePDFs;

use DateTimeImmutable;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use RuntimeException;

class Pdf
{
    private GeneratePDFs $client;

    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $status,
        private readonly string $downloadUrl,
        private readonly DateTimeImmutable $createdAt,
        GeneratePDFs $client
    ) {
        $this->client = $client;
    }

    /**
     * Create a Pdf instance from API response data.
     *
     * @param array<string, mixed> $data API response data
     * @param GeneratePDFs $client The GeneratePDFs client instance
     * @return self
     */
    public static function fromArray(array $data, GeneratePDFs $client): self
    {
        if (! isset($data['id'], $data['name'], $data['status'], $data['download_url'], $data['created_at'])) {
            throw new InvalidArgumentException('Invalid PDF data structure');
        }

        // Try parsing with ATOM format first (standard ISO 8601)
        $createdAt = DateTimeImmutable::createFromFormat(
            DateTimeImmutable::ATOM,
            $data['created_at']
        );

        // If that fails, try parsing with microseconds (ISO 8601 with microseconds)
        if ($createdAt === false) {
            $createdAt = DateTimeImmutable::createFromFormat(
                'Y-m-d\TH:i:s.u\Z',
                $data['created_at']
            );
        }

        // If that fails, try using the constructor which can parse ISO 8601
        if ($createdAt === false) {
            try {
                $createdAt = new DateTimeImmutable($data['created_at']);
            } catch (\Exception $e) {
                throw new InvalidArgumentException('Invalid created_at format: ' . $data['created_at']);
            }
        }

        return new self(
            (int) $data['id'],
            (string) $data['name'],
            (string) $data['status'],
            (string) $data['download_url'],
            $createdAt,
            $client
        );
    }

    /**
     * Get the PDF ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the PDF name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the PDF status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get the download URL.
     *
     * @return string
     */
    public function getDownloadUrl(): string
    {
        return $this->downloadUrl;
    }

    /**
     * Get the creation date.
     *
     * @return DateTimeImmutable
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Check if the PDF is ready for download.
     *
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Download the PDF content.
     *
     * @return string PDF binary content
     * @throws GuzzleException If the HTTP request fails
     * @throws RuntimeException If the PDF is not ready or download fails
     */
    public function download(): string
    {
        if (! $this->isReady()) {
            throw new RuntimeException("PDF is not ready yet. Current status: {$this->status}");
        }

        return $this->client->downloadPdf($this->downloadUrl);
    }

    /**
     * Download the PDF and save it to a file.
     *
     * @param string $filePath Path where to save the PDF file
     * @return bool True on success
     * @throws GuzzleException If the HTTP request fails
     * @throws RuntimeException If the PDF is not ready or download fails
     */
    public function downloadToFile(string $filePath): bool
    {
        $content = $this->download();

        $result = @file_put_contents($filePath, $content);

        if ($result === false) {
            throw new RuntimeException("Failed to write PDF to file: {$filePath}");
        }

        return true;
    }

    /**
     * Refresh the PDF data from the API.
     *
     * @return Pdf A new Pdf instance with updated data
     * @throws GuzzleException If the HTTP request fails
     * @throws InvalidArgumentException If the API response is invalid
     */
    public function refresh(): Pdf
    {
        return $this->client->getPdf($this->id);
    }
}
