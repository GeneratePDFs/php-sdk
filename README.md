# GeneratePDFs PHP SDK

PHP SDK for the [GeneratePDFs.com](https://generatepdfs.com) API, your go-to place for HTML to PDF.

Upload your HTML files, along with any CSS files and images to generate a PDF. Alternatively provide a URL to generate a PDF from it's contents.

## Installation

```bash
composer require generatepdfs/php-sdk
```

## Get your API Token

Sign up for an account on [GeneratePDFs.com](https://generatepdfs.com) and head to the API Tokens section and create a new token. 

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

## Testing

To run the test suite and code style checker, execute:

```bash
composer test
```

This will run both PHP CodeSniffer (PSR-2 standard) and Pest tests.

## Contributing

Contributions and suggestions are **welcome** and will be fully **credited**.

We accept contributions via Pull Requests on [GitHub](https://github.com/GeneratePDFs/php-sdk).

### Pull Requests

- **[PSR-12 Extended Coding Standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-12-extended-coding-style-guide.md)** - The easiest way to apply the conventions is to install [PHP Code Sniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/).
- **Add tests!** - Your patch won't be accepted if it doesn't have tests.
- **Document any change in behaviour** - Make sure the README / CHANGELOG and any other relevant documentation are kept up-to-date.
- **Consider our release cycle** - We try to follow semver. Randomly breaking public APIs is not an option.
- **Create topic branches** - Don't ask us to pull from your master branch.
- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.
- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please squash them before submitting.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a history of changes.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

