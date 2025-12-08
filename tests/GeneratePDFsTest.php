<?php

declare(strict_types=1);

use GeneratePDFs\GeneratePDFs;
use GeneratePDFs\Pdf;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    $this->apiToken = 'test-api-token';
    $this->baseUrl = 'https://api.generatepdfs.com';
});

test('connect creates a new GeneratePDFs instance', function () {
    $client = GeneratePDFs::connect($this->apiToken);

    expect($client)->toBeInstanceOf(GeneratePDFs::class);
});

test('generateFromHtml throws exception when HTML file does not exist', function () {
    $client = GeneratePDFs::connect($this->apiToken);

    expect(fn () => $client->generateFromHtml('/non/existent/file.html'))
        ->toThrow(InvalidArgumentException::class, 'HTML file not found or not readable');
});

test('generateFromHtml throws exception when HTML file is not readable', function () {
    $client = GeneratePDFs::connect($this->apiToken);
    $tempFile = sys_get_temp_dir().'/test-'.uniqid().'.html';

    // Create file with no read permissions
    touch($tempFile);
    chmod($tempFile, 0000);

    try {
        expect(fn () => $client->generateFromHtml($tempFile))
            ->toThrow(InvalidArgumentException::class, 'HTML file not found or not readable');
    } finally {
        @chmod($tempFile, 0644);
        @unlink($tempFile);
    }
});

test('generateFromHtml throws exception when CSS file does not exist', function () {
    $client = GeneratePDFs::connect($this->apiToken);
    $htmlFile = sys_get_temp_dir().'/test-'.uniqid().'.html';

    file_put_contents($htmlFile, '<html><body>Test</body></html>');

    try {
        expect(fn () => $client->generateFromHtml($htmlFile, '/non/existent/file.css'))
            ->toThrow(InvalidArgumentException::class, 'CSS file not found or not readable');
    } finally {
        @unlink($htmlFile);
    }
});

test('generateFromHtml successfully generates PDF from HTML file', function () {
    $htmlFile = sys_get_temp_dir().'/test-'.uniqid().'.html';
    file_put_contents($htmlFile, '<html><body>Test</body></html>');

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = new Response(200, [], json_encode([
        'data' => [
            'id' => 123,
            'name' => 'test.pdf',
            'status' => 'pending',
            'download_url' => 'https://api.generatepdfs.com/pdfs/123/download/token',
            'created_at' => '2024-01-01T12:00:00.000000Z',
        ],
    ]));

    $mockClient->shouldReceive('post')
        ->once()
        ->with(
            '/pdfs/generate',
            Mockery::on(function ($options) {
                return isset($options['headers']['Authorization'])
                    && isset($options['headers']['Content-Type'])
                    && isset($options['json']['html']);
            })
        )
        ->andReturn($mockResponse);

    $client = GeneratePDFs::connect($this->apiToken);
    $reflection = new ReflectionClass($client);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($client, $mockClient);

    $pdf = $client->generateFromHtml($htmlFile);

    expect($pdf)->toBeInstanceOf(Pdf::class)
        ->and($pdf->getId())->toBe(123)
        ->and($pdf->getName())->toBe('test.pdf')
        ->and($pdf->getStatus())->toBe('pending');

    @unlink($htmlFile);
});

test('generateFromHtml includes CSS when provided', function () {
    $htmlFile = sys_get_temp_dir().'/test-'.uniqid().'.html';
    $cssFile = sys_get_temp_dir().'/test-'.uniqid().'.css';

    file_put_contents($htmlFile, '<html><body>Test</body></html>');
    file_put_contents($cssFile, 'body { color: red; }');

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = new Response(200, [], json_encode([
        'data' => [
            'id' => 123,
            'name' => 'test.pdf',
            'status' => 'pending',
            'download_url' => 'https://api.generatepdfs.com/pdfs/123/download/token',
            'created_at' => '2024-01-01T12:00:00.000000Z',
        ],
    ]));

    $mockClient->shouldReceive('post')
        ->once()
        ->with(
            '/pdfs/generate',
            Mockery::on(function ($options) {
                return isset($options['json']['html'])
                    && isset($options['json']['css']);
            })
        )
        ->andReturn($mockResponse);

    $client = GeneratePDFs::connect($this->apiToken);
    $reflection = new ReflectionClass($client);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($client, $mockClient);

    $pdf = $client->generateFromHtml($htmlFile, $cssFile);

    expect($pdf)->toBeInstanceOf(Pdf::class);

    @unlink($htmlFile);
    @unlink($cssFile);
});

test('generateFromHtml includes images when provided', function () {
    $htmlFile = sys_get_temp_dir().'/test-'.uniqid().'.html';
    $imageFile = sys_get_temp_dir().'/test-'.uniqid().'.png';

    file_put_contents($htmlFile, '<html><body>Test</body></html>');
    file_put_contents($imageFile, 'fake-image-content');

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = new Response(200, [], json_encode([
        'data' => [
            'id' => 123,
            'name' => 'test.pdf',
            'status' => 'pending',
            'download_url' => 'https://api.generatepdfs.com/pdfs/123/download/token',
            'created_at' => '2024-01-01T12:00:00.000000Z',
        ],
    ]));

    $mockClient->shouldReceive('post')
        ->once()
        ->with(
            '/pdfs/generate',
            Mockery::on(function ($options) {
                return isset($options['json']['html'])
                    && isset($options['json']['images'])
                    && is_array($options['json']['images'])
                    && count($options['json']['images']) > 0;
            })
        )
        ->andReturn($mockResponse);

    $client = GeneratePDFs::connect($this->apiToken);
    $reflection = new ReflectionClass($client);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($client, $mockClient);

    $pdf = $client->generateFromHtml($htmlFile, null, [
        [
            'name' => 'test.png',
            'path' => $imageFile,
        ],
    ]);

    expect($pdf)->toBeInstanceOf(Pdf::class);

    @unlink($htmlFile);
    @unlink($imageFile);
});

test('generateFromHtml throws exception when API response is invalid', function () {
    $htmlFile = sys_get_temp_dir().'/test-'.uniqid().'.html';
    file_put_contents($htmlFile, '<html><body>Test</body></html>');

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = new Response(200, [], json_encode([
        // Missing 'data' key
    ]));

    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn($mockResponse);

    $client = GeneratePDFs::connect($this->apiToken);
    $reflection = new ReflectionClass($client);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($client, $mockClient);

    expect(fn () => $client->generateFromHtml($htmlFile))
        ->toThrow(InvalidArgumentException::class, 'Invalid API response: missing data');

    @unlink($htmlFile);
});

test('generateFromUrl throws exception for invalid URL', function () {
    $client = GeneratePDFs::connect($this->apiToken);

    expect(fn () => $client->generateFromUrl('not-a-valid-url'))
        ->toThrow(InvalidArgumentException::class, 'Invalid URL');
});

test('generateFromUrl successfully generates PDF from URL', function () {
    $mockClient = Mockery::mock(Client::class);
    $mockResponse = new Response(200, [], json_encode([
        'data' => [
            'id' => 456,
            'name' => 'url-example.com-2024-01-01-12-00-00.pdf',
            'status' => 'pending',
            'download_url' => 'https://api.generatepdfs.com/pdfs/456/download/token',
            'created_at' => '2024-01-01T12:00:00.000000Z',
        ],
    ]));

    $mockClient->shouldReceive('post')
        ->once()
        ->with(
            '/pdfs/generate',
            Mockery::on(function ($options) {
                return isset($options['json']['url'])
                    && $options['json']['url'] === 'https://example.com';
            })
        )
        ->andReturn($mockResponse);

    $client = GeneratePDFs::connect($this->apiToken);
    $reflection = new ReflectionClass($client);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($client, $mockClient);

    $pdf = $client->generateFromUrl('https://example.com');

    expect($pdf)->toBeInstanceOf(Pdf::class)
        ->and($pdf->getId())->toBe(456)
        ->and($pdf->getName())->toBe('url-example.com-2024-01-01-12-00-00.pdf');
});

test('downloadPdf successfully downloads PDF content', function () {
    $downloadUrl = 'https://api.generatepdfs.com/pdfs/123/download/token';
    $pdfContent = '%PDF-1.4 fake pdf content';

    $mockClient = Mockery::mock(Client::class);
    $mockResponse = new Response(200, [], $pdfContent);

    $mockClient->shouldReceive('get')
        ->once()
        ->with(
            $downloadUrl,
            Mockery::on(function ($options) {
                return isset($options['headers']['Authorization']);
            })
        )
        ->andReturn($mockResponse);

    $client = GeneratePDFs::connect($this->apiToken);
    $reflection = new ReflectionClass($client);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($client, $mockClient);

    $content = $client->downloadPdf($downloadUrl);

    expect($content)->toBe($pdfContent);
});

test('generateFromHtml handles Guzzle exceptions', function () {
    $htmlFile = sys_get_temp_dir().'/test-'.uniqid().'.html';
    file_put_contents($htmlFile, '<html><body>Test</body></html>');

    $mockClient = Mockery::mock(Client::class);
    $request = new Request('POST', '/pdfs/generate');
    $response = new Response(400);
    $exception = new ClientException('Bad Request', $request, $response);

    $mockClient->shouldReceive('post')
        ->once()
        ->andThrow($exception);

    $client = GeneratePDFs::connect($this->apiToken);
    $reflection = new ReflectionClass($client);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($client, $mockClient);

    expect(fn () => $client->generateFromHtml($htmlFile))
        ->toThrow(ClientException::class);

    @unlink($htmlFile);
});

test('getPdf throws exception for invalid ID', function () {
    $client = GeneratePDFs::connect($this->apiToken);

    expect(fn () => $client->getPdf(0))
        ->toThrow(InvalidArgumentException::class, 'Invalid PDF ID: 0');

    expect(fn () => $client->getPdf(-1))
        ->toThrow(InvalidArgumentException::class, 'Invalid PDF ID: -1');
});

test('getPdf successfully retrieves PDF by ID', function () {
    $mockClient = Mockery::mock(Client::class);
    $mockResponse = new Response(200, [], json_encode([
        'data' => [
            'id' => 789,
            'name' => 'retrieved.pdf',
            'status' => 'completed',
            'download_url' => 'https://api.generatepdfs.com/pdfs/789/download/token',
            'created_at' => '2024-01-01T12:00:00.000000Z',
        ],
    ]));

    $mockClient->shouldReceive('get')
        ->once()
        ->with(
            '/pdfs/789',
            Mockery::on(function ($options) {
                return isset($options['headers']['Authorization']);
            })
        )
        ->andReturn($mockResponse);

    $client = GeneratePDFs::connect($this->apiToken);
    $reflection = new ReflectionClass($client);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($client, $mockClient);

    $pdf = $client->getPdf(789);

    expect($pdf)->toBeInstanceOf(Pdf::class)
        ->and($pdf->getId())->toBe(789)
        ->and($pdf->getName())->toBe('retrieved.pdf')
        ->and($pdf->getStatus())->toBe('completed');
});

test('getPdf throws exception when API response is invalid', function () {
    $mockClient = Mockery::mock(Client::class);
    $mockResponse = new Response(200, [], json_encode([
        // Missing 'data' key
    ]));

    $mockClient->shouldReceive('get')
        ->once()
        ->andReturn($mockResponse);

    $client = GeneratePDFs::connect($this->apiToken);
    $reflection = new ReflectionClass($client);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($client, $mockClient);

    expect(fn () => $client->getPdf(123))
        ->toThrow(InvalidArgumentException::class, 'Invalid API response: missing data');
});

