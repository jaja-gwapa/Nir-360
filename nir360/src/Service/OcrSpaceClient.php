<?php
declare(strict_types=1);

/**
 * OCR.Space API client.
 * Sends images to https://api.ocr.space/parse/image and returns ParsedResults[0].ParsedText.
 * Requires OCR_SPACE_API_KEY in environment.
 */
class OcrSpaceClient
{
    private const API_URL = 'https://api.ocr.space/parse/image';

    private string $apiKey;
    private ?string $lastError = null;
    private ?int $lastHttpCode = null;

    public function __construct(string $apiKey)
    {
        $this->apiKey = trim($apiKey);
    }

    /** Last error message (for debugging / UI). */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /** Last HTTP status code (if any). */
    public function getLastHttpCode(): ?int
    {
        return $this->lastHttpCode;
    }

    /**
     * Run OCR on an image file and return extracted text.
     * Returns empty string on API error, invalid key, or when no text is found.
     *
     * @param string $imagePath Absolute path to image (JPEG/PNG).
     * @return string Extracted text from ParsedResults[0].ParsedText, or '' on failure.
     */
    public function parseImage(string $imagePath): string
    {
        $this->lastError = null;
        $this->lastHttpCode = null;

        if ($this->apiKey === '') {
            $this->lastError = 'OCR key is not set (OCR_SPACE_API_KEY / ocr_space_api_key).';
            error_log('[NIR360 OcrSpace] ' . $this->lastError);
            return '';
        }

        if (!is_readable($imagePath)) {
            $this->lastError = 'Image file not readable.';
            error_log('[NIR360 OcrSpace] ' . $this->lastError . ' path=' . $imagePath);
            return '';
        }

        $mime = mime_content_type($imagePath);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $this->lastError = 'Unsupported image type.';
            error_log('[NIR360 OcrSpace] ' . $this->lastError . ' mime=' . $mime);
            return '';
        }

        $detected = $mime;
        $ext = ($detected === 'image/png') ? 'png' : 'jpg';
        $cfile = new CURLFile($imagePath, $detected, 'image.' . $ext);

        $post = [
            'apikey' => $this->apiKey,
            'file' => $cfile,
            'language' => 'eng',
        ];

        $ch = curl_init(self::API_URL);
        if ($ch === false) {
            error_log('[NIR360 OcrSpace] curl_init failed.');
            return '';
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            // Many Windows XAMPP setups lack a CA bundle; avoid SSL failures locally.
            // If you want strict TLS verification, set a CA bundle in php.ini (curl.cainfo) and remove these.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: NIR360/1.0'],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastHttpCode = $httpCode;
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->lastError = $curlError !== '' ? $curlError : 'cURL request failed.';
            error_log('[NIR360 OcrSpace] Request failed: ' . $this->lastError);
            return '';
        }

        if ($httpCode !== 200) {
            $this->lastError = 'OCR API returned HTTP ' . $httpCode . '.';
            error_log('[NIR360 OcrSpace] ' . $this->lastError . ' Response: ' . substr($response, 0, 500));
            return '';
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->lastError = 'Invalid OCR API response (not JSON).';
            error_log('[NIR360 OcrSpace] ' . $this->lastError);
            return '';
        }

        if (!empty($data['IsErroredOnProcessing'])) {
            $msg = $data['ErrorMessage'] ?? 'Unknown API error';
            if (isset($data['ParsedResults'][0]['ErrorMessage']) && $data['ParsedResults'][0]['ErrorMessage'] !== '') {
                $msg = $data['ParsedResults'][0]['ErrorMessage'];
            }
            $this->lastError = is_array($msg) ? implode(' ', array_map('strval', $msg)) : (string)$msg;
            if ($this->lastError === '') {
                $this->lastError = 'OCR API error.';
            }
            error_log('[NIR360 OcrSpace] API error: ' . $this->lastError);
            return '';
        }

        if (empty($data['ParsedResults']) || !is_array($data['ParsedResults'])) {
            $this->lastError = 'OCR returned no parsed results.';
            return '';
        }

        $first = $data['ParsedResults'][0];
        $text = isset($first['ParsedText']) ? trim((string) $first['ParsedText']) : '';

        if ($text === '' && isset($first['ErrorMessage']) && $first['ErrorMessage'] !== '') {
            $this->lastError = (string)$first['ErrorMessage'];
            error_log('[NIR360 OcrSpace] Parsed result error: ' . $this->lastError);
        }

        return $text;
    }
}
