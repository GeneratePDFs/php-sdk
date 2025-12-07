# GeneratePDFs PHP SDK

PHP SDK for the GeneratePDFs.com API. Easily generate PDFs from HTML files or URLs.

## Installation

```bash
composer require generatepdfs/php-sdk
```

## Usage

### Basic Setup

```php
use GeneratePDFs\GeneratePDFs;

$client = GeneratePDFs::connect('YOUR_API_TOKEN');
```

### Generate PDF from HTML File

```php
use GeneratePDFs\GeneratePDFs;
use GeneratePDFs\Pdf;

// Simple HTML file
$pdf = $client->generateFromHtml('/path/to/file.html');

// HTML file with CSS
$pdf = $client->generateFromHtml(
    '/path/to/file.html',
    '/path/to/file.css'
);

// HTML file with CSS and images
$pdf = $client->generateFromHtml(
    '/path/to/file.html',
    '/path/to/file.css',
    [
        [
            'name' => 'logo.png',
            'path' => '/path/to/logo.png',
            'mime_type' => 'image/png' // Optional, will be auto-detected
        ],
        [
            'name' => 'photo.jpg',
            'path' => '/path/to/photo.jpg'
        ]
    ]
);
```

### Generate PDF from URL

```php
$pdf = $client->generateFromUrl('https://example.com');
```

### Working with PDF Objects

The SDK returns `Pdf` objects that provide easy access to PDF information and downloading:

```php
// Access PDF properties
$pdfId = $pdf->getId();
$pdfName = $pdf->getName();
$status = $pdf->getStatus();
$downloadUrl = $pdf->getDownloadUrl();
$createdAt = $pdf->getCreatedAt();

// Check if PDF is ready
if ($pdf->isReady()) {
    // Download PDF content as string
    $pdfContent = $pdf->download();
    
    // Or save directly to file
    $pdf->downloadToFile('/path/to/save/output.pdf');
}
```

### PDF Object Methods

- `getId(): int` - Get the PDF ID
- `getName(): string` - Get the PDF filename
- `getStatus(): string` - Get the current status (pending, processing, completed, failed)
- `getDownloadUrl(): string` - Get the download URL
- `getCreatedAt(): DateTimeImmutable` - Get the creation date
- `isReady(): bool` - Check if the PDF is ready for download
- `download(): string` - Download and return PDF binary content
- `downloadToFile(string $filePath): bool` - Download and save PDF to a file

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP Client 7.0 or higher

## License

MIT

