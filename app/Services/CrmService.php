<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Support\Logger;

class CrmService
{
    private const RETRY_DELAY_MICROSECONDS = 1200000;

    private string $apiToken;
    private string $endpoint;
    private string $caBundlePath;

    public function __construct()
    {
        $this->apiToken = trim((string) ($_ENV['CRM_API_TOKEN'] ?? ''));
        $this->endpoint = trim((string) ($_ENV['CRM_API_ENDPOINT'] ?? ''));
        $this->caBundlePath = $this->resolveCaBundlePath();
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function isConfigured(): bool
    {
        return $this->endpoint !== '' && $this->apiToken !== '';
    }

    public function hasCaBundle(): bool
    {
        return $this->caBundlePath !== '';
    }

    public function getCaBundlePath(): string
    {
        return $this->caBundlePath;
    }

    public function previewLeadPayload(array $lead): array
    {
        $payload = $this->buildLeadPayload($lead);
        unset($payload['api_token']);

        return [
            'endpoint' => $this->endpoint,
            'tokenConfigured' => $this->apiToken !== '',
            'payload' => $payload,
        ];
    }

    public function pushLead(array $lead): array
    {
        if ($this->endpoint === '') {
            return $this->skipped('CRM_API_URL_MISSING', 'CRM API URL not configured');
        }

        if ($this->apiToken === '') {
            return $this->skipped('CRM_API_TOKEN_MISSING', 'CRM_API_TOKEN missing in environment');
        }

        if (!function_exists('curl_init')) {
            return $this->skipped('CURL_UNAVAILABLE', 'cURL extension is not available');
        }

        $crmRequestPayload = $this->buildLeadPayload($lead);
        $attempts = [];

        $firstAttempt = $this->executeAttempt($crmRequestPayload);
        $attempts[] = $firstAttempt;

        if (!(bool) $firstAttempt['success']) {
            usleep(self::RETRY_DELAY_MICROSECONDS);
            $attempts[] = $this->executeAttempt($crmRequestPayload);
        }

        $finalAttempt = $attempts[count($attempts) - 1] ?? [
            'success' => false,
            'status' => '',
            'message' => 'No attempt executed',
        ];

        if ((bool) $finalAttempt['success']) {
            Logger::info('CRM API sync succeeded.', [
                'status' => (string) ($finalAttempt['status'] ?? ''),
            ]);
        } else {
            Logger::error('CRM API sync failed.', [
                'status' => (string) ($finalAttempt['status'] ?? ''),
                'message' => (string) ($finalAttempt['message'] ?? ''),
            ]);
        }

        return [
            'attempted' => true,
            'success' => (bool) ($finalAttempt['success'] ?? false),
            'status' => (string) ($finalAttempt['status'] ?? ''),
            'code' => (string) ($finalAttempt['status'] ?? ''),
            'message' => (string) ($finalAttempt['message'] ?? ''),
            'endpoint' => $this->endpoint,
            'attempts' => $attempts,
        ];
    }

    private function buildLeadPayload(array $lead): array
    {
        $dateOfBirth = $this->normalizeDate($lead['date_of_birth'] ?? $lead['dateOfBirth'] ?? $lead['dob'] ?? null);
        $dateOfAnniversary = $this->normalizeDate($lead['date_of_anniversary'] ?? $lead['dateOfAnniversary'] ?? $lead['anniversary'] ?? null);

        return [
            'api_token' => $this->apiToken,
            'contact_name' => trim((string) ($lead['name'] ?? '')),
            'contact_email' => trim((string) ($lead['email'] ?? '')),
            'contact_phone' => $this->toPlusInternationalPhone(
                (string) ($lead['phone'] ?? ''),
                (string) ($lead['country_code'] ?? '91')
            ),
            'prize' => trim((string) ($lead['prize'] ?? '')),
            'status' => trim((string) ($lead['status'] ?? 'Unredeemed')),
            'source' => trim((string) ($lead['source'] ?? 'menu-blocker-web')),
            'visit_count' => (int) ($lead['visit_count'] ?? $lead['visitCount'] ?? 1),
            'date_of_birth' => $dateOfBirth,
            'dob' => $dateOfBirth,
            'date_of_anniversary' => $dateOfAnniversary,
            'anniversary_date' => $dateOfAnniversary,
            'lead_timestamp' => $this->normalizeTimestamp($lead['lead_timestamp'] ?? $lead['created_at'] ?? null),
        ];
    }

    private function executeAttempt(array $crmRequestPayload): array
    {
        $ch = curl_init($this->endpoint);
        if ($ch === false) {
            return [
                'success' => false,
                'status' => '',
                'message' => 'Unable to initialize cURL',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POSTFIELDS => http_build_query($crmRequestPayload, '', '&'),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($this->caBundlePath !== '') {
            curl_setopt($ch, CURLOPT_CAINFO, $this->caBundlePath);
        }

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== '') {
            return [
                'success' => false,
                'status' => '',
                'message' => substr($curlError, 0, 500),
            ];
        }

        $message = trim((string) $responseBody);
        if ($message === '') {
            $message = $httpCode >= 200 && $httpCode < 300
                ? 'CRM sync completed with empty response.'
                : 'CRM sync failed with empty response.';
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode > 0 ? (string) $httpCode : '',
            'message' => substr($message, 0, 500),
        ];
    }

    private function skipped(string $code, string $message): array
    {
        return [
            'attempted' => false,
            'success' => false,
            'status' => '',
            'code' => $code,
            'message' => $message,
            'endpoint' => $this->endpoint,
            'attempts' => [],
        ];
    }

    private function normalizeDate($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeTimestamp($value): string
    {
        if ($value === null || $value === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return date('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function toPlusInternationalPhone(string $phone, string $countryCode): string
    {
        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
        $countryDigits = preg_replace('/\D+/', '', $countryCode) ?? '';

        if ($phoneDigits === '') {
            return '';
        }

        if (strlen($phoneDigits) === 10) {
            $phoneDigits = ($countryDigits !== '' ? $countryDigits : '91') . $phoneDigits;
        }

        if (str_starts_with($phoneDigits, '0')) {
            $phoneDigits = ltrim($phoneDigits, '0');
        }

        return '+' . $phoneDigits;
    }

    private function resolveCaBundlePath(): string
    {
        $candidates = [
            trim((string) ($_ENV['CRM_CA_BUNDLE_PATH'] ?? '')),
            trim((string) ini_get('curl.cainfo')),
            trim((string) ini_get('openssl.cafile')),
        ];

        foreach ($candidates as $candidate) {
            $resolved = $this->normalizeCaBundlePath($candidate);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return '';
    }

    private function normalizeCaBundlePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\\\')) {
            return is_file($path) ? $path : '';
        }

        $rootPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\\\'], DIRECTORY_SEPARATOR, $path);
        return is_file($rootPath) ? $rootPath : '';
    }
}