<?php
require __DIR__ . './vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->post('/extract-text', function (Request $request, Response $response) {
    $uploadedFiles = $request->getUploadedFiles();

    if (!isset($uploadedFiles['image'])) {
        $response->getBody()->write(json_encode(['error' => 'No image uploaded']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $imageFile = $uploadedFiles['image'];

    if ($imageFile->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode(['error' => 'Upload error']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // Save uploaded file temporarily
    $tmpFile = sys_get_temp_dir() . '/' . uniqid() . '_' . $imageFile->getClientFilename();
    $imageFile->moveTo($tmpFile);

    // Run Tesseract OCR command on saved image
    $outputFile = tempnam(sys_get_temp_dir(), 'ocr_output');
    $command = sprintf('tesseract %s %s', escapeshellarg($tmpFile), escapeshellarg($outputFile));
    exec($command);

    // Read the generated text file
    $text = @file_get_contents($outputFile . '.txt');

    // Clean up
    unlink($tmpFile);
    unlink($outputFile . '.txt');

    if ($text === false) {
        $response->getBody()->write(json_encode(['error' => 'Failed to read OCR output']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    $text = preg_replace('/\s+/', ' ', $text);
    $response->getBody()->write(json_encode(['text' => trim($text)]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
