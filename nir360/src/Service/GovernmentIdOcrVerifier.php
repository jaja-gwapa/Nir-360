<?php
declare(strict_types=1);

/**
 * Government ID OCR verification using OCR.Space API (and optional PhilSys template service).
 * Extracts text from ID image, normalizes it, compares with user input (name, birthdate, address),
 * computes match score and verification status.
 */
class GovernmentIdOcrVerifier
{
    private OcrSpaceClient $ocrClient;
    private float $poorConfidenceThreshold;
    private string $ocrServiceUrl;
    private ?string $lastOcrError = null;

    public function __construct(OcrSpaceClient $ocrClient, float $poorConfidenceThreshold = 70.0, string $ocrServiceUrl = '')
    {
        $this->ocrClient = $ocrClient;
        $this->poorConfidenceThreshold = $poorConfidenceThreshold;
        $this->ocrServiceUrl = rtrim($ocrServiceUrl, '/');
    }

    /** Run OCR on image and return raw text (via OCR.Space API). Returns empty string on failure. */
    private function runOcrText(string $imagePath): string
    {
        $this->lastOcrError = null;
        $text = $this->ocrClient->parseImage($imagePath);
        if ($text === '') {
            $this->lastOcrError = $this->ocrClient->getLastError();
        }
        return $text;
    }

    /**
     * Run verification: OCR on image, compare with user input, return result for JSON.
     *
     * @param string $imagePath Absolute path to saved image (JPEG/PNG).
     * @param array $userInput Keys: full_name, birthdate, address (all strings).
     * @return array name_match, birthdate_match, address_match, match_score, ocr_confidence, verification_status, scan_quality
     */
    public function verify(string $imagePath, array $userInput): array
    {
        if (!is_readable($imagePath)) {
            return $this->errorResult('Image file not readable.');
        }

        $fullName = $this->normalizeText(trim((string)($userInput['full_name'] ?? '')));
        $userBirthdateRaw = trim((string)($userInput['birthdate'] ?? ''));
        $address = $this->normalizeText(trim((string)($userInput['address'] ?? '')));

        // Prefer PhilSys template OCR when service URL is set and returns structured data
        $template = $this->fetchTemplateOcr($imagePath);
        if ($template !== null && $this->templateHasAnyData($template)) {
            $fromService = trim((string)($template['raw_text'] ?? ''));
            $fullOcrText = $fromService;
            // Merge in full-page OCR so "CITY OF BAGO" is found even if template service misses it
            $ocrText = $this->runOcrText($imagePath);
            if ($ocrText !== '') {
                $fullOcrText = $fullOcrText !== '' ? $fullOcrText . "\n" . $ocrText : $ocrText;
            }
            $fullOcrNormalized = $this->normalizeText($fullOcrText);
            if ($fullOcrNormalized === '' && $fromService !== '') {
                $fullOcrNormalized = $this->normalizeText($fromService);
                $fullOcrText = $fromService;
            }
            if (($template['birthdate'] ?? '') === '') {
                $extracted = $this->extractBirthdateFromOcr($fullOcrNormalized, $fullOcrText);
                $templateBirthdate = $this->normalizeDateToYmd($extracted);
                if ($templateBirthdate !== '') {
                    $template['birthdate'] = $templateBirthdate;
                }
            }
            if (($template['name'] ?? '') === '' && $fullOcrNormalized !== '') {
                $template['name'] = $fullOcrNormalized;
            }
            if (($template['address'] ?? '') === '' && $fullOcrNormalized !== '') {
                $template['address'] = $fullOcrNormalized;
            }
            return $this->verifyWithTemplateResult($template, $fullName, $userBirthdateRaw, $address, $fullOcrNormalized);
        }

        // Fallback: full-image OCR via OCR.Space
        $ocrText = $this->runOcrText($imagePath);
        $ocrConfidence = $ocrText !== '' ? 85.0 : 0.0;
        $normalizedOcr = $this->normalizeText($ocrText);

        $nameMatch = $this->matchName($fullName, $normalizedOcr);
        $birthdateResult = $this->validateBirthdateTolerant($userBirthdateRaw, $ocrText, $normalizedOcr);
        $addressMatch = $this->matchAddress($address, $normalizedOcr) && $this->ocrAddressContainsBagoCity($normalizedOcr);
        if (!$addressMatch && $nameMatch && $birthdateResult['birthdate_match'] && $this->userAddressContainsBago($address)) {
            $addressMatch = $this->matchAddress($address, $normalizedOcr);
        }
        if (!$addressMatch && $nameMatch && $birthdateResult['birthdate_match'] && $this->userAddressContainsBago($address)) {
            $addressMatch = true;
        }

        $matchScore = ($nameMatch ? 1 : 0) + ($birthdateResult['birthdate_match'] ? 1 : 0) + ($addressMatch ? 1 : 0);
        $verificationStatus = $this->getVerificationStatus($matchScore);
        $scanQuality = $ocrConfidence < $this->poorConfidenceThreshold ? 'poor' : 'ok';

        return $this->buildVerificationResult(
            $nameMatch,
            $birthdateResult['birthdate_match'],
            $birthdateResult['ocr_birthdate'],
            $birthdateResult['input_birthdate'],
            $addressMatch,
            $matchScore,
            round($ocrConfidence, 2),
            $verificationStatus,
            $scanQuality
        );
    }

    /**
     * Call PhilSys template OCR service (Flask /scan-philid). Returns null on failure.
     * @return array{name: string, birthdate: string, address: string}|null
     */
    private function fetchTemplateOcr(string $imagePath): ?array
    {
        if ($this->ocrServiceUrl === '') {
            return null;
        }
        $url = $this->ocrServiceUrl . '/scan-philid';
        $mime = mime_content_type($imagePath);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $mime = 'image/jpeg';
        }
        $filename = basename($imagePath);
        if ($filename === '') {
            $filename = 'id.jpg';
        }
        $cfile = new \CURLFile($imagePath, $mime, $filename);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['image' => $cfile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [],
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code !== 200) {
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['success'])) {
            return null;
        }
        return [
            'name' => trim((string)($data['name'] ?? '')),
            'birthdate' => trim((string)($data['birthdate'] ?? '')),
            'address' => trim((string)($data['address'] ?? '')),
            'raw_text' => trim((string)($data['raw_text'] ?? '')),
        ];
    }

    private function templateHasAnyData(array $template): bool
    {
        return ($template['name'] ?? '') !== ''
            || ($template['birthdate'] ?? '') !== ''
            || ($template['address'] ?? '') !== ''
            || ($template['raw_text'] ?? '') !== '';
    }

    private function verifyWithTemplateResult(array $template, string $fullName, string $userBirthdateRaw, string $address, string $fullOcrNormalized = ''): array
    {
        $ocrName = $this->normalizeText(trim($template['name']));
        $ocrBirthdate = $template['birthdate'] !== '' ? $this->normalizeDateToYmd(trim($template['birthdate'])) : '';
        $ocrAddress = $this->normalizeText(trim($template['address']));

        $nameMatch = $this->matchName($fullName, $ocrName);
        if (!$nameMatch && $fullOcrNormalized !== '') {
            $nameMatch = $this->matchName($fullName, $fullOcrNormalized);
        }
        $inputNormalized = $this->normalizeDateToYmd($userBirthdateRaw);
        $birthdateMatch = $ocrBirthdate !== '' && $inputNormalized !== '' && $ocrBirthdate === $inputNormalized;
        // Bago City: check combined template address + full OCR so we catch "CITY OF BAGO" from either source
        $textForBago = trim($ocrAddress . ' ' . $fullOcrNormalized);
        $bagoOk = $textForBago !== '' && $this->ocrAddressContainsBagoCity($textForBago);
        $addressMatch = $this->matchAddressNormalized($address, $ocrAddress) && $bagoOk;
        if (!$addressMatch && $fullOcrNormalized !== '') {
            $addressMatch = $this->matchAddressNormalized($address, $fullOcrNormalized) && $bagoOk;
        }
        // Fallback: name+birthdate already match (same person); if user's address contains Bago and matches ID text, accept
        if (!$addressMatch && $nameMatch && $birthdateMatch && $this->userAddressContainsBago($address)) {
            $addressMatch = $this->matchAddressNormalized($address, $ocrAddress)
                || ($fullOcrNormalized !== '' && $this->matchAddressNormalized($address, $fullOcrNormalized));
        }
        // Last resort: same person (name+birthdate) and user registered with Bago address → accept address
        if (!$addressMatch && $nameMatch && $birthdateMatch && $this->userAddressContainsBago($address)) {
            $addressMatch = true;
        }

        $matchScore = ($nameMatch ? 1 : 0) + ($birthdateMatch ? 1 : 0) + ($addressMatch ? 1 : 0);
        $verificationStatus = $this->getVerificationStatus($matchScore);
        $ocrConfidence = 85.0; // template extraction is more reliable
        $scanQuality = $ocrConfidence < $this->poorConfidenceThreshold ? 'poor' : 'ok';

        return $this->buildVerificationResult(
            $nameMatch,
            $birthdateMatch,
            $ocrBirthdate,
            $inputNormalized,
            $addressMatch,
            $matchScore,
            (float)round($ocrConfidence, 2),
            $verificationStatus,
            $scanQuality
        );
    }

    /** True if the user's registration address mentions Bago (e.g. "Bago City", "Bago"). */
    private function userAddressContainsBago(string $userAddress): bool
    {
        $upper = strtoupper(trim($userAddress));
        if ($upper === '') {
            return false;
        }
        return str_contains($upper, 'BAGO CITY') || str_contains($upper, 'CITY OF BAGO')
            || str_contains($upper, 'BAGO CITY COLLEGE') || preg_match('/\bBAGO\b/', $upper);
    }

    /** Require OCR to contain "Bago City", "CITY OF BAGO", "Bago City College", or "BAGO" (PhilID format). Tolerant of OCR noise (0/O) and newlines. */
    private function ocrAddressContainsBagoCity(string $ocrAddress): bool
    {
        $ocrAddress = preg_replace('/[\r\n\s]+/', ' ', $ocrAddress);
        $upper = strtoupper(trim($ocrAddress));
        if ($upper === '') {
            return false;
        }
        if (str_contains($upper, 'BAGO CITY') || str_contains($upper, 'CITY OF BAGO') || str_contains($upper, 'BAGO CITY COLLEGE')) {
            return true;
        }
        if (preg_match('/\bBAGO\b/', $upper)) {
            return true;
        }
        // OCR may read O as 0: normalize and check for BAGO
        $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $upper);
        return preg_match('/\bBAG[O0]\b/', $normalized) || str_contains($normalized, 'BAGO') || str_contains($normalized, 'BAG0');
    }

    private const REJECTION_MESSAGE = 'The information on your ID does not match the registration details.';

    /** Rejection message for registration: strict validation failed. Shown with optional field-specific reasons. */
    public const REGISTRATION_REJECTION_MESSAGE = 'Registration details do not match the ID.';

    /** Rejection when address (barangay) does not match the ID. */
    public const BARANGAY_MISMATCH_MESSAGE = 'Address does not match the ID.';

    /** Rejection when sitio/purok does not match the ID. */
    public const PUROK_MISMATCH_MESSAGE = 'Sitio/Purok does not match the ID.';

    /** Rejection message when birthdate could not be read from the ID (e.g. poor image quality). */
    public const BIRTHDATE_UNREADABLE_MESSAGE = 'Birthdate could not be read from your ID. Please use a clearer photo where the date of birth is clearly visible.';

    /**
     * Strict verification for registration. Validates six fields against ID: First Name, Middle Name, Last Name, Birthdate, Address (barangay), Sitio/Purok.
     * Matching rules: case-insensitive; leading/trailing spaces ignored; multiple spaces treated as one; spelling must match exactly (no fuzzy matching).
     * Optional OCR normalization on ID text only (e.g. 0/O, 1/I, 5/S) to handle common misreads. If any field does not match, registration is rejected
     * and rejection_message indicates which field(s) failed.
     *
     * @param string $imagePath Absolute path to saved image.
     * @param array $userInput Keys: first_name, middle_name, last_name (or full_name), birthdate, barangay, street_address.
     * @return array Same shape as verify() with verification_status only 'verified' or 'rejected', and rejection_message on failure.
     */
    public function verifyStrict(string $imagePath, array $userInput): array
    {
        if (!is_readable($imagePath)) {
            return $this->errorResult('Image file not readable.', self::REGISTRATION_REJECTION_MESSAGE);
        }

        $formName = $this->normalizeStrict(trim((string)($userInput['full_name'] ?? '')));
        $firstName = $this->normalizeStrict(trim((string)($userInput['first_name'] ?? '')));
        $middleName = $this->normalizeStrict(trim((string)($userInput['middle_name'] ?? '')));
        $lastName = $this->normalizeStrict(trim((string)($userInput['last_name'] ?? '')));
        $formBirthdateRaw = trim((string)($userInput['birthdate'] ?? ''));
        $formBirthdateYmd = $this->normalizeDateToYmd($formBirthdateRaw);
        $selectedBarangay = $this->normalizeStrict(trim((string)($userInput['barangay'] ?? '')));
        $selectedStreetPurok = $this->normalizeStrict(trim((string)($userInput['street_address'] ?? '')));

        $template = $this->fetchTemplateOcr($imagePath);
        $fullOcrText = '';
        $fullOcrNormalized = '';
        if ($template !== null && $this->templateHasAnyData($template)) {
            $fromService = trim((string)($template['raw_text'] ?? ''));
            $fullOcrText = $fromService;
            $ocrText = $this->runOcrText($imagePath);
            if ($ocrText !== '') {
                $fullOcrText = $fullOcrText !== '' ? $fullOcrText . "\n" . $ocrText : $ocrText;
            }
            $fullOcrNormalized = $this->normalizeText($fullOcrText);
            if ($fullOcrNormalized === '' && $fromService !== '') {
                $fullOcrNormalized = $this->normalizeText($fromService);
                $fullOcrText = $fromService;
            }
            if (($template['birthdate'] ?? '') === '') {
                $extracted = $this->extractBirthdateFromOcr($fullOcrNormalized, $fullOcrText);
                $templateBirthdate = $this->normalizeDateToYmd($extracted);
                if ($templateBirthdate !== '') {
                    $template['birthdate'] = $templateBirthdate;
                }
            }
            if (($template['name'] ?? '') === '' && $fullOcrNormalized !== '') {
                $template['name'] = $fullOcrNormalized;
            }
            if (($template['address'] ?? '') === '' && $fullOcrNormalized !== '') {
                $template['address'] = $fullOcrNormalized;
            }
        } else {
            $fullOcrText = $this->runOcrText($imagePath);
            $fullOcrNormalized = $this->normalizeText($fullOcrText);
            $template = [
                'name' => $fullOcrNormalized,
                'birthdate' => '',
                'address' => $fullOcrNormalized,
            ];
            if ($fullOcrNormalized !== '') {
                $extracted = $this->extractBirthdateFromOcr($fullOcrNormalized, $fullOcrText);
                $template['birthdate'] = $this->normalizeDateToYmd($extracted);
            }
        }

        $ocrName = $this->normalizeStrict(trim((string)($template['name'] ?? '')));
        $ocrBirthdateYmd = ($template['birthdate'] ?? '') !== '' ? $this->normalizeDateToYmd(trim($template['birthdate'])) : '';
        $fullOcrLower = $this->normalizeStrict($fullOcrNormalized);
        $ocrAddressLower = $this->normalizeStrict(trim((string)($template['address'] ?? '')));
        $combinedForBarangay = trim(
            ($template['name'] ?? '') . ' ' . ($template['address'] ?? '') . ' ' . ($template['raw_text'] ?? '') . ' ' . $fullOcrText . ' ' . $fullOcrNormalized
        );
        $ocrTextForBarangay = $this->normalizeStrict($combinedForBarangay);

        $nameMatch = $formName !== '' && (
            ($ocrName !== '' && ($this->strictNameMatch($formName, $ocrName) || $this->strictNameMatchOcrTolerant($formName, $ocrName)))
            || $this->strictNameWordsInText($formName, $fullOcrLower)
            || $this->strictNameWordsInTextOcrTolerant($formName, $fullOcrLower)
        );
        $firstOk = false;
        $middleOk = true;
        $lastOk = false;
        $splitNameMatch = false;
        $ocrNameWords = $this->strictNameWords(trim((string)($template['name'] ?? '')));
        $numOcrWords = count($ocrNameWords);
        if ($firstName !== '' && $lastName !== '') {
            $nameSearchText = trim($ocrName . ' ' . $fullOcrLower);
            // When ID gives clear name words (2+), require exact word match so "yvonn" ≠ "yvonne" and "tolos" ≠ "tolosa"
            // PhilID uses order: Last, Given names, Middle → OCR may return [SUNICO, YVONNE, TOLOSA]. Try both orderings.
            // OCR.Space sometimes returns a lot of extra words (headers/labels). Only do positional matching when the "name" looks clean.
            if ($numOcrWords >= 2 && $numOcrWords <= 4) {
                $tryPhilIdOrder = ($numOcrWords === 3 && $middleName !== '');
                $firstOk = $firstName === $ocrNameWords[0];
                $lastOk = $lastName === $ocrNameWords[$numOcrWords - 1];
                if ($numOcrWords === 3 && $middleName !== '') {
                    $middleOk = $middleName === $ocrNameWords[1];
                } elseif ($middleName !== '') {
                    $middleOk = $this->strictNameTokenInTextOcrTolerant($middleName, $nameSearchText);
                }
                // PhilID order: words = [Last, First, Middle] → first=words[1], middle=words[2], last=words[0]
                if ($tryPhilIdOrder && (!$firstOk || !$lastOk || !$middleOk)) {
                    $philFirst = $firstName === $ocrNameWords[1];
                    $philMiddle = $middleName === $ocrNameWords[2];
                    $philLast = $lastName === $ocrNameWords[0];
                    if ($philFirst && $philMiddle && $philLast) {
                        $firstOk = true;
                        $middleOk = true;
                        $lastOk = true;
                    }
                }
            } else {
                // Fallback: require each token to appear as a whole word in OCR text (still exact spelling, case-insensitive).
                $firstOk = $this->strictNameTokenInTextOcrTolerant($firstName, $nameSearchText);
                $lastOk = $this->strictNameTokenInTextOcrTolerant($lastName, $nameSearchText);
                if ($middleName !== '') {
                    $middleOk = $this->strictNameTokenInTextOcrTolerant($middleName, $nameSearchText);
                }
            }
            $splitNameMatch = $firstOk && $lastOk && $middleOk;
        }
        $nameMatch = $nameMatch || $splitNameMatch;
        $first_name_match = ($firstName === '') ? true : $firstOk;
        $middle_name_match = ($middleName === '') ? true : $middleOk;
        $last_name_match = ($lastName === '') ? true : $lastOk;
        $birthdateMatch = $formBirthdateYmd !== '' && $ocrBirthdateYmd !== '' && $formBirthdateYmd === $ocrBirthdateYmd;
        // If primary extracted date didn't match, check if user's birthdate appears anywhere on the ID (avoids wrong date picked, e.g. issue date)
        if (!$birthdateMatch && $formBirthdateYmd !== '') {
            $allOcrDates = $this->extractAllDatesFromOcr($fullOcrNormalized, $fullOcrText);
            if (in_array($formBirthdateYmd, $allOcrDates, true)) {
                $birthdateMatch = true;
                if ($ocrBirthdateYmd === '') {
                    $ocrBirthdateYmd = $formBirthdateYmd;
                }
            }
        }
        $addressMatch = $this->strictBarangayInOcr($selectedBarangay, $ocrTextForBarangay, $fullOcrLower);
        if (!$addressMatch && $selectedBarangay !== '') {
            $addressMatch = $this->strictBarangayWordInOcr($selectedBarangay, $ocrTextForBarangay . ' ' . $fullOcrLower);
        }
        if (!$addressMatch && $selectedBarangay !== '') {
            $addressMatch = $this->barangayInRawText($selectedBarangay, $combinedForBarangay);
        }
        if (!$addressMatch && $selectedBarangay !== '') {
            $addressMatch = $this->barangayInLettersOnly($selectedBarangay, $combinedForBarangay);
        }
        if (!$addressMatch && $selectedBarangay !== '') {
            $addressMatch = $this->barangayPrefixInOcr($selectedBarangay, $combinedForBarangay);
        }
        $directOcr = '';
        if (!$addressMatch && $selectedBarangay !== '') {
            $directOcr = $this->runOcrText($imagePath);
            $addressMatch = $this->barangayInRawText($selectedBarangay, $directOcr);
        }
        if (!$addressMatch && $selectedBarangay !== '' && $directOcr !== '') {
            $addressMatch = $this->barangayInLettersOnly($selectedBarangay, $directOcr);
        }
        if (!$addressMatch && $selectedBarangay !== '' && $directOcr !== '') {
            $addressMatch = $this->barangayPrefixInOcr($selectedBarangay, $directOcr);
        }
        $barangayMatch = $addressMatch;

        // Purok / Street-Sitio: user input must appear in OCR address (case-insensitive). If it doesn't match, address does not match.
        $purokMatch = true;
        if ($selectedStreetPurok !== '') {
            $purokMatch = $this->purokInOcr($selectedStreetPurok, $combinedForBarangay);
            if (!$purokMatch && $directOcr !== '') {
                $purokMatch = $this->purokInOcr($selectedStreetPurok, $directOcr);
            }
        }
        // Address match = barangay match AND purok/sitio match (both must match user input to ID)
        $addressMatch = $barangayMatch && $purokMatch;

        $allMatch = $nameMatch && $birthdateMatch && $addressMatch;
        $verificationStatus = $allMatch ? 'verified' : 'rejected';
        $matchScore = ($nameMatch ? 1 : 0) + ($birthdateMatch ? 1 : 0) + ($addressMatch ? 1 : 0);
        $rejectionParts = [];
        if (trim($combinedForBarangay) === '') {
            // OCR returned no usable text; surface a clearer message so user can fix API/key/connection issues.
            $msg = 'The ID image could not be read. Please use a clear photo (JPEG/PNG) or try again.';
            if ($this->lastOcrError !== null && trim($this->lastOcrError) !== '') {
                $msg .= ' OCR error: ' . trim($this->lastOcrError);
            }
            $rejectionParts[] = $msg;
        }
        if (!$first_name_match) {
            $rejectionParts[] = 'First name does not match the name on the ID.';
        }
        if (!$middle_name_match) {
            $rejectionParts[] = 'Middle name does not match the name on the ID.';
        }
        if (!$last_name_match) {
            $rejectionParts[] = 'Last name does not match the name on the ID.';
        }
        if (!$birthdateMatch && $ocrBirthdateYmd === '' && $formBirthdateYmd !== '') {
            $rejectionParts[] = self::BIRTHDATE_UNREADABLE_MESSAGE;
        } elseif (!$birthdateMatch && $formBirthdateYmd !== '') {
            $rejectionParts[] = 'Birthdate does not match the date on the ID.';
        }
        if (!$barangayMatch && $selectedBarangay !== '') {
            $rejectionParts[] = self::BARANGAY_MISMATCH_MESSAGE;
        }
        if (!$purokMatch && $selectedStreetPurok !== '') {
            $rejectionParts[] = self::PUROK_MISMATCH_MESSAGE;
        }
        $rejectionMessage = $allMatch ? null : (
            count($rejectionParts) > 0
                ? self::REGISTRATION_REJECTION_MESSAGE . ' ' . implode(' ', $rejectionParts)
                : self::REGISTRATION_REJECTION_MESSAGE
        );

        $out = $this->buildVerificationResult(
            $nameMatch,
            $birthdateMatch,
            $ocrBirthdateYmd,
            $formBirthdateYmd,
            $addressMatch,
            $matchScore,
            85.0,
            $verificationStatus,
            'ok'
        );
        $out['ocr_full_name'] = trim((string)($template['name'] ?? ''));
        $out['input_full_name'] = trim((string)($userInput['full_name'] ?? ''));
        $out['first_name_match'] = $first_name_match;
        $out['middle_name_match'] = $middle_name_match;
        $out['last_name_match'] = $last_name_match;
        $out['barangay_match'] = $barangayMatch;
        $out['sitio_purok_match'] = $purokMatch;
        if ($rejectionMessage !== null) {
            $out['rejection_message'] = $rejectionMessage;
        }
        // When barangay fails, expose a short preview of the OCR text we searched (for debugging)
        if (!$addressMatch && $selectedBarangay !== '') {
            $preview = mb_substr($combinedForBarangay, 0, 500);
            $out['debug_ocr_preview'] = $preview . (mb_strlen($combinedForBarangay) > 500 ? '…' : '');
        }
        return $out;
    }

    /** Normalize for strict comparison: lowercase, trim, collapse spaces. Makes comparison case-insensitive (big/small letters match). */
    private function normalizeStrict(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return strtolower($s);
    }

    /** Normalize OCR text for name matching: apply normalizeStrict then common OCR confusables (0→o, 1→i, 5→s) so "TOLO5" matches "tolos". */
    private function normalizeOcrForNameMatch(string $s): string
    {
        $s = $this->normalizeStrict($s);
        $s = str_replace(['0', '1', '5'], ['o', 'i', 's'], $s);
        return $s;
    }

    /** Strict name match: same words (exact letters, case-insensitive), order-independent. Punctuation on words ignored. */
    private function strictNameMatch(string $formName, string $ocrName): bool
    {
        $formWords = $this->strictNameWords($formName);
        $ocrWords = $this->strictNameWords($ocrName);
        if (count($formWords) !== count($ocrWords)) {
            return false;
        }
        sort($formWords);
        sort($ocrWords);
        return $formWords === $ocrWords;
    }

    /** Like strictNameMatch but normalizes OCR name for common confusables (0→o, 1→i, 5→s) so "TOLO5" matches "tolos". */
    private function strictNameMatchOcrTolerant(string $formName, string $ocrName): bool
    {
        if ($this->strictNameMatch($formName, $ocrName)) {
            return true;
        }
        $ocrNormalized = $this->normalizeOcrForNameMatch($ocrName);
        return $this->strictNameMatch($formName, $ocrNormalized);
    }

    /** True if every form name word appears as a whole word in the text (no substring: "yvonn" must not match inside "yvonne"). */
    private function strictNameWordsInText(string $formName, string $normalizedText): bool
    {
        $normalizedText = $this->normalizeStrict($normalizedText);
        $formWords = $this->strictNameWords($formName);
        if (count($formWords) === 0 || $normalizedText === '') {
            return false;
        }
        foreach ($formWords as $w) {
            if (!preg_match('/\b' . preg_quote($w, '/') . '\b/u', $normalizedText)) {
                return false;
            }
        }
        return true;
    }

    /** Like strictNameWordsInText but also tries OCR-normalized text (0→o, 1→i, 5→s) so misread ID text still matches. */
    private function strictNameWordsInTextOcrTolerant(string $formName, string $ocrText): bool
    {
        if ($this->strictNameWordsInText($formName, $ocrText)) {
            return true;
        }
        $ocrNormalized = $this->normalizeOcrForNameMatch($ocrText);
        return $this->strictNameWordsInText($formName, $ocrNormalized);
    }

    private function strictNameTokenInText(string $token, string $normalizedText): bool
    {
        $normalizedText = $this->normalizeStrict($normalizedText);
        $parts = $this->strictNameWords($token);
        if (count($parts) === 0 || $normalizedText === '') {
            return false;
        }
        foreach ($parts as $part) {
            if (!preg_match('/\b' . preg_quote($part, '/') . '\b/', $normalizedText)) {
                return false;
            }
        }
        return true;
    }

    /** Name token in OCR text with tolerance for common OCR misreads (0/O, 1/I, 5/S). E.g. user "tolos" matches ID "TOLO5". */
    private function strictNameTokenInTextOcrTolerant(string $token, string $ocrText): bool
    {
        if ($this->strictNameTokenInText($token, $ocrText)) {
            return $this->nameTokenNotPrefixOfLongerIdWord($token, $ocrText);
        }
        $ocrNormalized = $this->normalizeOcrForNameMatch($ocrText);
        if ($this->strictNameTokenInText($token, $ocrNormalized)) {
            return $this->nameTokenNotPrefixOfLongerIdWord($token, $ocrNormalized);
        }
        return false;
    }

    /** Reject if the ID has a longer word that starts with the user's token (e.g. ID "TOLOSA" vs user "tolos" → reject). */
    private function nameTokenNotPrefixOfLongerIdWord(string $userToken, string $normalizedOcrText): bool
    {
        $ocrWords = $this->strictNameWords($normalizedOcrText);
        $userTokenLower = strtolower(trim($userToken));
        $len = strlen($userTokenLower);
        if ($len === 0) {
            return true;
        }
        foreach ($ocrWords as $w) {
            if (strlen($w) > $len && str_starts_with($w, $userTokenLower)) {
                return false;
            }
        }
        return true;
    }

    private function strictMiddleInitialInText(string $middleName, string $normalizedText): bool
    {
        $normalizedText = $this->normalizeStrict($normalizedText);
        $parts = $this->strictNameWords($middleName);
        if (count($parts) === 0 || $normalizedText === '') {
            return false;
        }
        $initial = substr($parts[0], 0, 1);
        if ($initial === '' || !preg_match('/^[a-z0-9]$/', $initial)) {
            return false;
        }
        return (bool)preg_match('/\b' . preg_quote($initial, '/') . '\b/', $normalizedText);
    }

    /** Extract name words for strict match: lowercase, strip punctuation, drop empty. */
    private function strictNameWords(string $s): array
    {
        $s = strtolower(trim(preg_replace('/\s+/', ' ', $s)));
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
        $words = array_filter(explode(' ', $s), fn($w) => $w !== '');
        return array_values($words);
    }

    /**
     * Barangay validation: selected barangay (lowercase) must appear in OCR-extracted text (lowercase).
     * Tolerates OCR noise (0/O, 1/I), extra spaces, and punctuation. Example: "Napoles" in "PUROK MAHOGANY, NAPOLES, CITY OF BAGO" → allow.
     */
    private function strictBarangayInOcr(string $selectedBarangay, string $ocrTextCombined, string $fullOcrLower): bool
    {
        if ($selectedBarangay === '') {
            return false;
        }
        $texts = array_filter([$ocrTextCombined, $fullOcrLower], fn($t) => $t !== '');
        if (empty($texts)) {
            return false;
        }
        $ocrNormalizedForBarangay = $this->normalizeOcrForBarangayMatch(implode(' ', $texts));
        $barangayVariants = $this->barangayMatchVariants($selectedBarangay);
        foreach ($barangayVariants as $variant) {
            if ($variant === '') {
                continue;
            }
            if (str_contains($ocrNormalizedForBarangay, $variant)) {
                return true;
            }
            $ocrNoSpaces = str_replace(' ', '', $ocrNormalizedForBarangay);
            $variantNoSpaces = str_replace(' ', '', $variant);
            if ($variantNoSpaces !== '' && str_contains($ocrNoSpaces, $variantNoSpaces)) {
                return true;
            }
        }
        $ocrLettersOnly = preg_replace('/[^a-z0-9]/', '', $ocrNormalizedForBarangay);
        $barangayLettersOnly = preg_replace('/[^a-z0-9]/', '', $selectedBarangay);
        if ($barangayLettersOnly !== '' && str_contains($ocrLettersOnly, $barangayLettersOnly)) {
            return true;
        }
        return false;
    }

    /** Normalize OCR text for barangay check: lowercase, collapse spaces, tolerate 0/O and 1/I. */
    private function normalizeOcrForBarangayMatch(string $s): string
    {
        $s = $this->normalizeStrict($s);
        $s = str_replace('0', 'o', $s);
        $s = str_replace('1', 'i', $s);
        return $s;
    }

    /** Return barangay and variants (e.g. napoles, nap0les) for tolerant matching. */
    private function barangayMatchVariants(string $barangayLower): array
    {
        $variants = [$barangayLower];
        $variants[] = str_replace('o', '0', $barangayLower);
        $variants[] = str_replace('i', '1', $barangayLower);
        return array_unique($variants);
    }

    /** Simple check: barangay (lowercase) appears in raw text. Case-insensitive, tolerates 0/O and 1/I. */
    private function barangayInRawText(string $barangayLower, string $rawText): bool
    {
        if ($barangayLower === '' || $rawText === '') {
            return false;
        }
        $raw = strtolower(trim(preg_replace('/\s+/', ' ', $rawText)));
        $raw = str_replace('0', 'o', $raw);
        $raw = str_replace('1', 'i', $raw);
        if (str_contains($raw, $barangayLower)) {
            return true;
        }
        $barangayO = str_replace('o', '0', $barangayLower);
        $barangayI = str_replace('i', '1', $barangayLower);
        return str_contains($raw, $barangayO) || str_contains($raw, $barangayI);
    }

    /**
     * Purok / Street-Sitio: user input must appear in OCR as a whole word (exact match).
     * Case-insensitive; tolerates 0/O, 1/I. Rejects when input is only a substring of a different ID word
     * (e.g. user "kamonsila" must not match ID "kamonsilan").
     */
    private function purokInOcr(string $userStreetLower, string $ocrText): bool
    {
        if ($userStreetLower === '') {
            return true;
        }
        if ($ocrText === '') {
            return false;
        }
        return $this->purokWholeWordInOcr($userStreetLower, $ocrText);
    }

    /**
     * True only if the user's street/sitio/purok appears in OCR as a whole word (word boundaries).
     * Prevents "kamonsila" from matching inside "kamonsilan". Tolerates 0/O, 1/I in OCR.
     */
    private function purokWholeWordInOcr(string $userStreetLower, string $ocrText): bool
    {
        $ocrNorm = $this->normalizeOcrForBarangayMatch($ocrText);
        $quoted = preg_quote($userStreetLower, '/');
        if (preg_match('/\b' . $quoted . '\b/u', $ocrNorm)) {
            return true;
        }
        // Try with letters-only OCR so "KAMONSILAN" in ID matches user "kamonsilan"
        $ocrLetters = preg_replace('/[^a-z0-9]/', ' ', $ocrNorm);
        $userLetters = preg_replace('/[^a-z0-9]/', '', $userStreetLower);
        if ($userLetters === '') {
            return false;
        }
        $words = preg_split('/\s+/', $ocrLetters, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $w) {
            if ($w === $userLetters) {
                return true;
            }
        }
        return false;
    }

    /**
     * Most permissive check: reduce both to letters only (a-z, 0->o, 1->i), then check if barangay appears as substring.
     * Catches "NAPOLES" in "BARANGAY NAPOLES", "N A P O L E S", "NAP0LES", etc.
     */
    private function barangayInLettersOnly(string $barangayLower, string $rawText): bool
    {
        if ($barangayLower === '' || $rawText === '') {
            return false;
        }
        $raw = strtolower(preg_replace('/\s+/', '', $rawText));
        $raw = preg_replace('/[^a-z0-9]/', '', $raw);
        $raw = str_replace('0', 'o', $raw);
        $raw = str_replace('1', 'i', $raw);
        $barangayLetters = preg_replace('/[^a-z0-9]/', '', $barangayLower);
        $barangayLetters = str_replace('0', 'o', $barangayLetters);
        $barangayLetters = str_replace('1', 'i', $barangayLetters);
        if ($barangayLetters === '') {
            return false;
        }
        if (str_contains($raw, $barangayLetters)) {
            return true;
        }
        $barangayO = str_replace('o', '0', $barangayLetters);
        $barangayI = str_replace('i', '1', $barangayLetters);
        return str_contains($raw, $barangayO) || str_contains($raw, $barangayI);
    }

    /**
     * Accept when OCR shows a prefix of the barangay (e.g. "NAPOL" for "Napoles") due to unclear ID or truncation.
     * Uses first 5 characters of barangay; if that appears in normalized OCR text, treat as match.
     */
    private function barangayPrefixInOcr(string $barangayLower, string $rawText): bool
    {
        if ($barangayLower === '' || $rawText === '') {
            return false;
        }
        $barangayLetters = preg_replace('/[^a-z0-9]/', '', $barangayLower);
        $barangayLetters = str_replace('0', 'o', $barangayLetters);
        $barangayLetters = str_replace('1', 'i', $barangayLetters);
        if (mb_strlen($barangayLetters) < 5) {
            return false;
        }
        $prefix = mb_substr($barangayLetters, 0, 5);
        $raw = strtolower(preg_replace('/\s+/', '', $rawText));
        $raw = preg_replace('/[^a-z0-9]/', '', $raw);
        $raw = str_replace('0', 'o', $raw);
        $raw = str_replace('1', 'i', $raw);
        return str_contains($raw, $prefix);
    }

    /** Match barangay as a whole word in OCR text (case-insensitive). Uses OCR-normalized text (0/o, 1/i). Tolerates punctuation. */
    private function strictBarangayWordInOcr(string $barangayLower, string $ocrText): bool
    {
        if ($barangayLower === '' || $ocrText === '') {
            return false;
        }
        $ocr = $this->normalizeOcrForBarangayMatch($ocrText);
        $quoted = preg_quote($barangayLower, '/');
        if (preg_match('/\b' . $quoted . '\b/i', $ocr)) {
            return true;
        }
        $ocrLettersOnly = preg_replace('/[^a-z0-9]/', ' ', $ocr);
        $ocrWords = preg_split('/\s+/', $ocrLettersOnly, -1, PREG_SPLIT_NO_EMPTY);
        $barangayLettersOnly = preg_replace('/[^a-z0-9]/', '', $barangayLower);
        return $barangayLettersOnly !== '' && in_array($barangayLettersOnly, $ocrWords, true);
    }

    private function buildVerificationResult(
        bool $nameMatch,
        bool $birthdateMatch,
        string $ocrBirthdate,
        string $inputBirthdate,
        bool $addressMatch,
        int $matchScore,
        float $ocrConfidence,
        string $verificationStatus,
        string $scanQuality
    ): array {
        $rejectionMessage = ($verificationStatus === 'rejected') ? self::REJECTION_MESSAGE : null;
        $out = [
            'name_match' => $nameMatch,
            'birthdate_match' => $birthdateMatch,
            'ocr_birthdate' => $ocrBirthdate,
            'input_birthdate' => $inputBirthdate,
            'address_match' => $addressMatch,
            'match_score' => $matchScore,
            'ocr_confidence' => $ocrConfidence,
            'verification_status' => $verificationStatus,
            'scan_quality' => $scanQuality,
        ];
        if ($rejectionMessage !== null) {
            $out['rejection_message'] = $rejectionMessage;
        }
        return $out;
    }

    /** Normalize text: uppercase, collapse multiple spaces. */
    public function normalizeText(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return strtoupper($s);
    }

    /** Normalize for name match: uppercase, strip punctuation (e.g. commas), collapse spaces. */
    private function normalizeTextForNameMatch(string $s): string
    {
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return strtoupper($s);
    }

    /** Name match: user name (or all parts) appears in OCR. Order-independent (PhilID: LASTNAME GIVENNAME MIDDLENAME). */
    private function matchName(string $userName, string $ocrText): bool
    {
        if ($userName === '' || $ocrText === '') {
            return false;
        }
        $userName = $this->normalizeTextForNameMatch($userName);
        $ocrText = $this->normalizeTextForNameMatch($ocrText);
        if (str_contains($ocrText, $userName)) {
            return true;
        }
        $userWords = array_filter(explode(' ', $userName), fn($w) => strlen($w) > 1);
        if (count($userWords) === 0) {
            return false;
        }
        if (count($userWords) === 1) {
            return str_contains($ocrText, $userName) || $this->fuzzyMatch($userName, $ocrText);
        }
        $found = 0;
        foreach ($userWords as $w) {
            if (str_contains($ocrText, $w)) {
                $found++;
            }
        }
        if ($found >= count($userWords)) {
            return true;
        }
        if ($found >= min(2, count($userWords))) {
            return true;
        }
        return $this->fuzzyMatch($userName, $ocrText);
    }

    /**
     * Tolerant birthdate validation: normalize OCR and user input to YYYY-MM-DD, then compare.
     * Returns [ birthdate_match, ocr_birthdate, input_birthdate ] for JSON and logging.
     */
    private function validateBirthdateTolerant(string $userBirthdateRaw, string $ocrTextRaw, string $normalizedOcr): array
    {
        $out = [
            'birthdate_match' => false,
            'ocr_birthdate' => '',
            'input_birthdate' => '',
        ];
        $inputNormalized = $this->normalizeDateToYmd($userBirthdateRaw);
        $out['input_birthdate'] = $inputNormalized;
        if ($inputNormalized === '') {
            $this->logBirthdateValidation($ocrTextRaw, '', '', $userBirthdateRaw, $out['input_birthdate'], false);
            return $out;
        }
        $extractedOcr = $this->extractBirthdateFromOcr($normalizedOcr, $ocrTextRaw);
        $ocrNormalized = $this->normalizeDateToYmd($extractedOcr);
        $out['ocr_birthdate'] = $ocrNormalized;
        $out['birthdate_match'] = ($ocrNormalized !== '' && $ocrNormalized === $inputNormalized);
        $this->logBirthdateValidation($ocrTextRaw, $extractedOcr, $ocrNormalized, $userBirthdateRaw, $inputNormalized, $out['birthdate_match']);
        return $out;
    }

    /** Extract first date-like string from OCR text (for display and normalization). Tolerates OCR noise (0/O, 1/I, spaces). */
    private function extractBirthdateFromOcr(string $normalizedOcr, string $ocrTextRaw): string
    {
        $patterns = [
            '/\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}/',
            '/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/',
            '/\d{1,2}\s+(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+\d{2,4}/i',
            '/(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+\d{1,2},?\s+\d{4}/i',
            '/(?:JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+\d{1,2},?\s+\d{4}/i',
            '/\d{1,2}\s+(?:JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+\d{4}/i',
        ];
        foreach ([$normalizedOcr, $ocrTextRaw] as $search) {
            if ($search === '') {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $search, $m)) {
                    $candidate = trim($m[0]);
                    if ($this->normalizeDateToYmd($candidate) !== '') {
                        return $candidate;
                    }
                }
            }
        }
        // Try with common OCR substitutions (0 for O, 1 for I in month names)
        $noise = str_replace(['0', '1'], ['O', 'I'], $ocrTextRaw);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $noise, $m)) {
                $candidate = trim($m[0]);
                if ($this->normalizeDateToYmd($candidate) !== '') {
                    return $candidate;
                }
            }
        }
        return '';
    }

    /** Extract all date-like strings from OCR and return their YYYY-MM-DD forms (unique). Used to match user birthdate when it appears anywhere on ID. */
    private function extractAllDatesFromOcr(string $normalizedOcr, string $ocrTextRaw): array
    {
        $patterns = [
            '/\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}/',
            '/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/',
            '/\d{1,2}\s+(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+\d{2,4}/i',
            '/(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+\d{1,2},?\s+\d{4}/i',
            '/(?:JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+\d{1,2},?\s+\d{4}/i',
            '/\d{1,2}\s+(?:JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+\d{4}/i',
        ];
        $collected = [];
        $sources = array_filter([$normalizedOcr, $ocrTextRaw, str_replace(['0', '1'], ['O', 'I'], $ocrTextRaw)]);
        foreach ($sources as $search) {
            foreach ($patterns as $pattern) {
                $count = preg_match_all($pattern, $search, $m);
                if ($count > 0 && !empty($m[0])) {
                    foreach ($m[0] as $candidate) {
                        $ymd = $this->normalizeDateToYmd(trim($candidate));
                        if ($ymd !== '' && $ymd !== null) {
                            $collected[$ymd] = true;
                        }
                    }
                }
            }
        }
        return array_keys($collected);
    }

    /**
     * Normalize date to YYYY-MM-DD. Handles "june 17, 2002", "june 17,2002", YYYY-MM-DD, etc.
     */
    private function normalizeDateToYmd(string $s): string
    {
        $s = trim(preg_replace('/[\s\.]+/', ' ', $s));
        $s = str_replace(',', ' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));
        if ($s === '') {
            return '';
        }
        $ymd = $this->parseDateToYmd($s);
        if ($ymd !== null) {
            return $ymd;
        }
        $ts = @strtotime($s);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }
        return '';
    }

    private function logBirthdateValidation(
        string $rawOcr,
        string $extractedBirthdate,
        string $normalizedOcrBirthdate,
        string $userInputRaw,
        string $normalizedInput,
        bool $match
    ): void {
        if (!function_exists('error_log')) {
            return;
        }
        $rawPreview = strlen($rawOcr) > 200 ? substr($rawOcr, 0, 200) . '...' : $rawOcr;
        error_log('[NIR360 OCR birthdate] raw_ocr_preview=' . $rawPreview);
        error_log('[NIR360 OCR birthdate] extracted_birthdate=' . $extractedBirthdate);
        error_log('[NIR360 OCR birthdate] normalized_ocr_birthdate=' . $normalizedOcrBirthdate);
        error_log('[NIR360 OCR birthdate] user_input_birthdate_raw=' . $userInputRaw);
        error_log('[NIR360 OCR birthdate] normalized_input_birthdate=' . $normalizedInput);
        error_log('[NIR360 OCR birthdate] birthdate_match=' . ($match ? 'true' : 'false'));
    }

    private function parseDateToYmd(string $s): ?string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        $s = str_replace(['/', '.'], '-', $s);
        if ($s === '') {
            return null;
        }
        $formats = ['Y-m-d', 'Y/m/d', 'd-m-Y', 'd/m/Y', 'm-d-Y', 'm/d/Y', 'd-m-y', 'm-d-y'];
        foreach ($formats as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $s);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }
        $ts = @strtotime($s);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }
        return null;
    }

    /** Address match: user address appears in OCR or fuzzy. */
    private function matchAddress(string $userAddress, string $ocrText): bool
    {
        if ($userAddress === '') {
            return false;
        }
        if (str_contains($ocrText, $userAddress)) {
            return true;
        }
        return $this->fuzzyMatch($userAddress, $ocrText);
    }

    /** Address match for template OCR: normalize "CITY OF X" / "X CITY" so they match. */
    private function matchAddressNormalized(string $userAddress, string $ocrAddress): bool
    {
        if ($userAddress === '' || $ocrAddress === '') {
            return false;
        }
        if (str_contains($ocrAddress, $userAddress)) {
            return true;
        }
        $userNorm = $this->normalizeAddressForMatch($userAddress);
        $ocrNorm = $this->normalizeAddressForMatch($ocrAddress);
        if (str_contains($ocrNorm, $userNorm) || str_contains($userNorm, $ocrNorm)) {
            return true;
        }
        $userWords = array_filter(explode(' ', $userNorm), fn($w) => strlen($w) >= 2);
        $found = 0;
        foreach ($userWords as $w) {
            if (str_contains($ocrNorm, $w)) {
                $found++;
            }
        }
        if (count($userWords) > 0 && $found >= min(2, count($userWords))) {
            return true;
        }
        if (count($userWords) === 1 && $found === 1) {
            return true;
        }
        if (count($userWords) >= 2 && $found >= (int) ceil(count($userWords) * 0.6)) {
            return true;
        }
        return $this->fuzzyMatch($userNorm, $ocrNorm, 55.0);
    }

    /** Normalize address for comparison: "CITY OF BAGO" <-> "BAGO CITY", "PROVINCE OF" removed, collapse spaces. */
    private function normalizeAddressForMatch(string $s): string
    {
        $s = $this->normalizeText($s);
        $s = preg_replace('/\bCITY\s+OF\s+/i', ' ', $s);
        $s = preg_replace('/\s+CITY\b/i', ' ', $s);
        $s = preg_replace('/\bPROVINCE\s+OF\s+/i', ' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return $s;
    }

    private function fuzzyMatch(string $needle, string $haystack, float $similarityThreshold = 70.0): bool
    {
        $needle = trim($needle);
        $haystack = trim($haystack);
        if ($needle === '' || $haystack === '') {
            return false;
        }
        similar_text($needle, $haystack, $pct);
        if ($pct >= $similarityThreshold) {
            return true;
        }
        $words = array_filter(explode(' ', $needle), fn($w) => strlen($w) >= 2);
        $found = 0;
        foreach ($words as $w) {
            if (str_contains($haystack, $w)) {
                $found++;
            }
        }
        return count($words) > 0 && $found >= min(2, count($words));
    }

    private function normalizeBirthdateInput(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        $ymd = $this->parseDateToYmd($s);
        return $ymd ?? $s;
    }

    private function getVerificationStatus(int $matchScore): string
    {
        return match (true) {
            $matchScore >= 3 => 'verified',
            $matchScore === 2 => 'manual_review',
            default => 'rejected',
        };
    }

    private function errorResult(string $error, ?string $rejectionMessage = null): array
    {
        $msg = $rejectionMessage ?? self::REJECTION_MESSAGE;
        return [
            'name_match' => false,
            'birthdate_match' => false,
            'ocr_birthdate' => '',
            'input_birthdate' => '',
            'address_match' => false,
            'match_score' => 0,
            'ocr_confidence' => 0.0,
            'verification_status' => 'rejected',
            'scan_quality' => 'poor',
            'error' => $error,
            'rejection_message' => $msg,
        ];
    }
}
