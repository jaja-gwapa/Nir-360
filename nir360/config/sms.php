<?php
/**
 * SMS notifications via HTTPSMS API (Philippines).
 * Configure via env: SMS_API_URL, SMS_USERNAME, SMS_PASSWORD (or SMS_API_KEY).
 * Phone numbers are normalized to 09XXXXXXXXX (11 digits).
 */
declare(strict_types=1);

/**
 * Normalize phone number to Philippines mobile format 09XXXXXXXXX (11 digits).
 * Accepts 09xxxxxxxxx, 639xxxxxxxxx, +639xxxxxxxxx, 9xxxxxxxxx.
 */
function normalizePhilippinesMobile(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        return '0' . $digits;
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        return $digits;
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
        return '0' . substr($digits, 2);
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '9')) {
        return '0' . $digits;
    }
    return $digits;
}

/**
 * Get SMS config from environment or config/sms.local.php (override).
 */
function getSmsConfig(): array
{
    $apiUrl = getenv('SMS_API_URL') ?: '';
    $username = getenv('SMS_USERNAME') ?: '';
    $password = getenv('SMS_PASSWORD') ?: '';
    $apiKey = getenv('SMS_API_KEY') ?: '';
    $local = __DIR__ . '/sms.local.php';
    $from = '';
    if (file_exists($local)) {
        $localConfig = (require $local);
        if (is_array($localConfig)) {
            if (!empty($localConfig['api_url'])) {
                $apiUrl = (string) $localConfig['api_url'];
            }
            if (isset($localConfig['username'])) {
                $username = (string) $localConfig['username'];
            }
            if (isset($localConfig['password'])) {
                $password = (string) $localConfig['password'];
            }
            if (!empty($localConfig['api_key'])) {
                $apiKey = (string) $localConfig['api_key'];
            }
            if (!empty($localConfig['from'])) {
                $from = (string) $localConfig['from'];
            }
        }
    }
    return ['api_url' => $apiUrl, 'username' => $username, 'password' => $password, 'api_key' => $apiKey, 'from' => $from];
}

/**
 * Send SMS via HTTPSMS API. Returns true on success, false on failure.
 * $to: recipient number (will be normalized to 09XXXXXXXXX).
 */
function sendSMS(string $to, string $message): bool
{
    $to = normalizePhilippinesMobile($to);
    if (strlen($to) !== 11 || !str_starts_with($to, '09')) {
        return false;
    }

    $cfg = getSmsConfig();
    $apiUrl = $cfg['api_url'];
    $username = $cfg['username'];
    $password = $cfg['password'];
    $apiKey = $cfg['api_key'];

    if ($apiUrl === '' || ($username === '' && $apiKey === '')) {
        return false;
    }

    $message = trim($message);
    if ($message === '') {
        return false;
    }

    $isHttpsms = (strpos($apiUrl, 'httpsms.com') !== false);

    if ($isHttpsms && $apiKey !== '') {
        // httpSMS API: x-api-key header + JSON body with content, from, to (E.164: +63... for Philippines)
        $cfg = getSmsConfig();
        $from = $cfg['from'] ?? '';
        if ($from === '') {
            return false;
        }
        $fromE164 = toE164($from);
        $toE164 = toE164($to);
        $payload = [
            'content' => $message,
            'from' => $fromE164,
            'to' => $toE164,
        ];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'x-api-key: ' . $apiKey,
            ],
        ]);
    } else {
        // Generic HTTPSMS-style: form body or api_key in body
        if ($apiKey !== '') {
            $data = ['api_key' => $apiKey, 'to' => $to, 'message' => $message];
        } else {
            $data = ['un' => $username, 'pwd' => $password, 'dstno' => $to, 'msg' => $message, 'type' => '1'];
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
    }

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err !== '') {
        return false;
    }
    return $code >= 200 && $code < 300;
}

/**
 * Convert 09XXXXXXXXX to E.164 +63XXXXXXXXX for httpSMS.
 */
function toE164(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        return '+63' . substr($digits, 1);
    }
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        return '+63' . $digits;
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
        return '+' . $digits;
    }
    return '+' . $digits;
}
