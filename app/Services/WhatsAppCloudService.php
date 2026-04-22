<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Repositories\ApiSettingsRepository;
use NK\Repositories\EventTransactionRepository;
use NK\Repositories\WhatsAppEventMappingRepository;
use NK\Repositories\WhatsAppMessageLogRepository;
use NK\Repositories\WhatsAppScheduledMessageRepository;
use NK\Repositories\WhatsAppTemplateDraftRepository;
use NK\Repositories\WhatsAppTemplateRepository;
use NK\Support\Logger;
use NK\Support\SiteUrl;

class WhatsAppCloudService
{
    private const API_VERSION = 'v23.0';
    private const CONFIG_KEYS = [
        'WHATSAPP_META_ACCESS_TOKEN',
        'WHATSAPP_META_PHONE_NUMBER_ID',
        'WHATSAPP_META_BUSINESS_ACCOUNT_ID',
        'WHATSAPP_META_VERIFY_TOKEN',
    ];

    private const EVENT_CATALOG = [
        ['eventKey' => 'event_registration_confirmed', 'label' => 'Event Registration Confirmed', 'description' => 'Send a utility template after a free registration or paid booking is confirmed.', 'sampleVariables' => ['customer_name', 'event_title', 'event_date', 'event_time', 'transaction_id', 'booking_type']],
        ['eventKey' => 'winner_coupon_issued', 'label' => 'Winner Coupon Issued', 'description' => 'Send a utility template when a spin winner completes the flow and receives a coupon.', 'sampleVariables' => ['customer_name', 'reward_label', 'coupon_code']],
        ['eventKey' => 'try_again_surprise_issued', 'label' => 'Try Again Surprise Issued', 'description' => 'Send a utility template after staff manually issues a surprise reward to a Try Again customer.', 'sampleVariables' => ['customer_name', 'reward_label', 'coupon_code']],
        ['eventKey' => 'coupon_redeemed', 'label' => 'Coupon Redeemed', 'description' => 'Send a utility template after staff redeems a winner or surprise reward.', 'sampleVariables' => ['customer_name', 'reward_label', 'coupon_code']],
        ['eventKey' => 'guest_checked_in', 'label' => 'Guest Checked In', 'description' => 'Send a utility template after a guest ticket or QR pass is checked in successfully.', 'sampleVariables' => ['customer_name', 'event_title', 'admitted_count', 'checked_in_at']],
        ['eventKey' => 'event_reminder_24h_image', 'label' => 'Event Reminder 24h', 'description' => 'Send the 24-hour event reminder with the event image header.', 'sampleVariables' => ['customer_name', 'event_title', 'event_date', 'event_time']],
        ['eventKey' => 'event_reminder_6h_image', 'label' => 'Event Reminder 6h', 'description' => 'Send the 6-hour event reminder with the event image header.', 'sampleVariables' => ['customer_name', 'event_title', 'event_date', 'event_time']],
        ['eventKey' => 'event_reminder_2h_image', 'label' => 'Event Reminder 2h', 'description' => 'Send the 2-hour event reminder with the event image header.', 'sampleVariables' => ['customer_name', 'event_title', 'event_date', 'event_time']],
        ['eventKey' => 'event_checkin_pending_30m', 'label' => 'No Check-In 30m', 'description' => 'Send a reminder when the event started 30 minutes ago and the guest has not checked in.', 'sampleVariables' => ['customer_name', 'event_title', 'event_time']],
        ['eventKey' => 'event_checkin_thank_you_12h', 'label' => 'Check-In Thank You 12h', 'description' => 'Send a thank-you message 12 hours after a successful check-in.', 'sampleVariables' => ['customer_name', 'event_title']],
    ];

    private ApiSettingsRepository $apiSettings;
    private EventTransactionRepository $transactions;
    private WhatsAppTemplateRepository $templates;
    private WhatsAppTemplateDraftRepository $drafts;
    private WhatsAppEventMappingRepository $mappings;
    private WhatsAppMessageLogRepository $logs;
    private WhatsAppScheduledMessageRepository $scheduled;
    private array $config;

    public function __construct()
    {
        $this->apiSettings = new ApiSettingsRepository();
        $this->transactions = new EventTransactionRepository();
        $this->templates = new WhatsAppTemplateRepository();
        $this->drafts = new WhatsAppTemplateDraftRepository();
        $this->mappings = new WhatsAppEventMappingRepository();
        $this->logs = new WhatsAppMessageLogRepository();
        $this->scheduled = new WhatsAppScheduledMessageRepository();
        $this->config = $this->loadConfig();
    }

    public static function eventCatalog(): array
    {
        return self::EVENT_CATALOG;
    }

    public function getWorkspace(int $logLimit = 20): array
    {
        $mappings = $this->mappings->listAllIndexed();
        $templates = $this->templates->listApproved();
        $allTemplates = $this->templates->listAll();
        $this->refreshDraftStatusesFromTemplates($allTemplates);
        $logs = $this->logs->listLatest($logLimit);
        $drafts = $this->drafts->listLatest(20);
        $upcoming = $this->scheduled->listUpcoming(20);

        return [
            'config' => [
                'accessTokenConfigured' => $this->config['WHATSAPP_META_ACCESS_TOKEN'] !== '',
                'phoneNumberIdConfigured' => $this->config['WHATSAPP_META_PHONE_NUMBER_ID'] !== '',
                'businessAccountIdConfigured' => $this->config['WHATSAPP_META_BUSINESS_ACCOUNT_ID'] !== '',
                'verifyTokenConfigured' => $this->config['WHATSAPP_META_VERIFY_TOKEN'] !== '',
                'readyForSync' => $this->isReadyForTemplateSync(),
                'readyForSend' => $this->isReadyForSend(),
                'webhookUrl' => rtrim(SiteUrl::resolveRuntime('home'), '/') . '/?action=whatsapp_webhook',
            ],
            'events' => array_map(function (array $event) use ($mappings): array {
                $mapping = $mappings[$event['eventKey']] ?? null;
                return [
                    'eventKey' => $event['eventKey'],
                    'label' => $event['label'],
                    'description' => $event['description'],
                    'sampleVariables' => $event['sampleVariables'],
                    'mapping' => [
                        'templateName' => (string) ($mapping['template_name'] ?? ''),
                        'languageCode' => (string) ($mapping['language_code'] ?? ''),
                        'isEnabled' => !empty($mapping['is_enabled']),
                        'updatedBy' => (string) ($mapping['updated_by'] ?? ''),
                        'updatedAt' => (string) ($mapping['updated_at'] ?? ''),
                    ],
                ];
            }, self::EVENT_CATALOG),
            'templates' => array_map([$this, 'formatTemplateForWorkspace'], $templates),
            'logs' => array_map([$this, 'formatLogForWorkspace'], $logs),
            'drafts' => array_map([$this, 'formatDraftForWorkspace'], $drafts),
            'scheduledMessages' => array_map([$this, 'formatScheduleForWorkspace'], $upcoming),
            'scheduleSummary' => $this->scheduled->summaryCounts(),
            'summary' => [
                'approvedTemplates' => count($templates),
                'storedTemplates' => count($allTemplates),
                'draftTemplates' => count($drafts),
            ],
        ];
    }

    public function syncApprovedTemplates(string $requestedBy): array
    {
        if (!$this->isReadyForTemplateSync()) {
            return ['ok' => false, 'error' => 'WHATSAPP_NOT_CONFIGURED', 'message' => 'Meta access token and business account ID are required before syncing templates.'];
        }

        $url = 'https://graph.facebook.com/' . self::API_VERSION . '/' . rawurlencode($this->config['WHATSAPP_META_BUSINESS_ACCOUNT_ID']) . '/message_templates?limit=100&fields=id,name,status,category,language,quality_score,components';
        $response = $this->executeJsonRequest('GET', $url);
        if (!($response['ok'] ?? false)) {
            Logger::error('WhatsApp template sync failed.', ['requestedBy' => $requestedBy, 'message' => (string) ($response['message'] ?? '')]);
            return $response;
        }

        $items = is_array($response['data']['data'] ?? null) ? $response['data']['data'] : [];
        $synced = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $this->templates->upsert([
                'template_uid' => (string) ($item['id'] ?? ''),
                'template_name' => (string) ($item['name'] ?? ''),
                'language_code' => (string) ($item['language'] ?? ''),
                'category' => (string) ($item['category'] ?? ''),
                'status' => strtoupper(trim((string) ($item['status'] ?? ''))),
                'quality_score' => is_array($item['quality_score'] ?? null) ? (string) (($item['quality_score']['score'] ?? '') ?: ($item['quality_score']['quality_rating'] ?? '')) : (string) ($item['quality_score'] ?? ''),
                'components_json' => json_encode($item['components'] ?? [], JSON_UNESCAPED_SLASHES),
                'last_synced_at' => date('Y-m-d H:i:s'),
            ]);
            $synced++;
        }

        $this->refreshDraftStatusesFromTemplates($this->templates->listAll());
        return ['ok' => true, 'message' => 'Synced ' . $synced . ' WhatsApp template(s) from Meta.', 'synced' => $synced];
    }

    public function saveEventMapping(string $eventKey, array $mapping, string $updatedBy): array
    {
        if (!$this->isKnownEvent($eventKey)) {
            return ['ok' => false, 'error' => 'INVALID_EVENT', 'message' => 'Unsupported WhatsApp event key.'];
        }

        $templateName = trim((string) ($mapping['templateName'] ?? $mapping['template_name'] ?? ''));
        $languageCode = trim((string) ($mapping['languageCode'] ?? $mapping['language_code'] ?? ''));
        $isEnabled = !empty($mapping['isEnabled']) || !empty($mapping['is_enabled']);
        if ($isEnabled && ($templateName === '' || $languageCode === '')) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Template name and language are required before enabling an event mapping.'];
        }
        if ($templateName !== '' && $languageCode !== '') {
            $template = $this->templates->findByNameAndLanguage($templateName, $languageCode);
            if (!$template) {
                return ['ok' => false, 'error' => 'TEMPLATE_NOT_FOUND', 'message' => 'Selected template is not available in the synced Meta template list.'];
            }
        }

        $this->mappings->upsert(['event_key' => $eventKey, 'template_name' => $templateName, 'language_code' => $languageCode, 'is_enabled' => $isEnabled, 'updated_by' => $updatedBy, 'updated_at' => date('Y-m-d H:i:s')]);
        return ['ok' => true, 'message' => 'WhatsApp event mapping saved.'];
    }

    public function saveTemplateDraft(array $draft, string $updatedBy): array
    {
        $templateName = $this->sanitizeTemplateName((string) ($draft['templateName'] ?? $draft['template_name'] ?? ''));
        $bodyText = trim((string) ($draft['bodyText'] ?? $draft['body_text'] ?? ''));
        if ($templateName === '' || $bodyText === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Template name and body text are required.'];
        }

        $id = (int) ($draft['id'] ?? 0);
        $payload = [
            'draft_name' => trim((string) ($draft['draftName'] ?? $draft['draft_name'] ?? $templateName)),
            'template_name' => $templateName,
            'category' => $this->normalizeTemplateCategory((string) ($draft['category'] ?? 'UTILITY')),
            'language_code' => trim((string) ($draft['languageCode'] ?? $draft['language_code'] ?? 'en')),
            'header_type' => $this->normalizeHeaderType((string) ($draft['headerType'] ?? $draft['header_type'] ?? 'NONE')),
            'header_text' => trim((string) ($draft['headerText'] ?? $draft['header_text'] ?? '')),
            'body_text' => $bodyText,
            'footer_text' => trim((string) ($draft['footerText'] ?? $draft['footer_text'] ?? '')),
            'buttons_json' => json_encode($draft['buttons'] ?? [], JSON_UNESCAPED_SLASHES),
            'sample_variables_json' => json_encode($draft['sampleVariables'] ?? [], JSON_UNESCAPED_SLASHES),
            'example_media_handle' => trim((string) ($draft['exampleMediaHandle'] ?? $draft['example_media_handle'] ?? '')),
            'status' => (string) ($draft['status'] ?? 'draft'),
            'meta_template_id' => (string) ($draft['metaTemplateId'] ?? $draft['meta_template_id'] ?? ''),
            'submitted_at' => $draft['submittedAt'] ?? null,
            'last_synced_at' => $draft['lastSyncedAt'] ?? null,
            'rejection_reason' => trim((string) ($draft['rejectionReason'] ?? $draft['rejection_reason'] ?? '')),
            'created_by' => $updatedBy,
            'updated_by' => $updatedBy,
        ];

        if ($id > 0) {
            $existing = $this->drafts->findById($id);
            if (!$existing) {
                return ['ok' => false, 'error' => 'NOT_FOUND', 'message' => 'Template draft not found.'];
            }
            $payload['created_by'] = (string) ($existing['created_by'] ?? $updatedBy);
            $this->drafts->update($id, $payload);
        } else {
            $id = $this->drafts->create($payload);
        }

        return ['ok' => true, 'message' => 'WhatsApp template draft saved.', 'draftId' => $id];
    }

    public function submitTemplateDraft(int $draftId, string $requestedBy): array
    {
        $draft = $this->drafts->findById($draftId);
        if (!$draft) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'message' => 'Template draft not found.'];
        }
        if (!$this->isReadyForTemplateSync()) {
            return ['ok' => false, 'error' => 'WHATSAPP_NOT_CONFIGURED', 'message' => 'Meta access token and business account ID are required before submitting a template.'];
        }

        $response = $this->executeJsonRequest('POST', 'https://graph.facebook.com/' . self::API_VERSION . '/' . rawurlencode($this->config['WHATSAPP_META_BUSINESS_ACCOUNT_ID']) . '/message_templates', $this->buildCreateTemplatePayload($draft));
        $now = date('Y-m-d H:i:s');
        $this->drafts->update($draftId, [
            'draft_name' => (string) ($draft['draft_name'] ?? ''),
            'template_name' => (string) ($draft['template_name'] ?? ''),
            'category' => (string) ($draft['category'] ?? 'UTILITY'),
            'language_code' => (string) ($draft['language_code'] ?? 'en'),
            'header_type' => (string) ($draft['header_type'] ?? 'NONE'),
            'header_text' => (string) ($draft['header_text'] ?? ''),
            'body_text' => (string) ($draft['body_text'] ?? ''),
            'footer_text' => (string) ($draft['footer_text'] ?? ''),
            'buttons_json' => $draft['buttons_json'] ?? null,
            'sample_variables_json' => $draft['sample_variables_json'] ?? null,
            'example_media_handle' => (string) ($draft['example_media_handle'] ?? ''),
            'status' => !empty($response['ok']) ? 'submitted' : 'submit_failed',
            'meta_template_id' => trim((string) (($response['data']['id'] ?? '') ?: ($draft['meta_template_id'] ?? ''))),
            'submitted_at' => $now,
            'last_synced_at' => $now,
            'rejection_reason' => !empty($response['ok']) ? '' : trim((string) ($response['message'] ?? 'Template submission failed.')),
            'updated_by' => $requestedBy,
        ]);

        return ['ok' => !empty($response['ok']), 'message' => !empty($response['ok']) ? 'Template draft submitted to Meta successfully.' : (string) ($response['message'] ?? 'Template submission failed.'), 'result' => $response];
    }

    public function scheduleEventReminders(array $event, array $transaction): void
    {
        $transactionId = trim((string) ($transaction['transaction_id'] ?? ''));
        $phone = trim((string) ($transaction['customer_phone'] ?? ''));
        if ($transactionId === '' || $phone === '') {
            return;
        }

        $eventStartAt = $this->buildEventStartAt((string) ($event['start_date'] ?? ''), (string) ($event['start_time'] ?? ''));
        if ($eventStartAt === null) {
            return;
        }

        $baseContext = [
            'customerName' => (string) ($transaction['customer_name'] ?? 'Guest'),
            'eventTitle' => (string) (($event['title'] ?? '') ?: ($transaction['event_title'] ?? 'Namaste Kalyan Event')),
            'eventDate' => $this->formatDateLabel((string) ($event['start_date'] ?? '')),
            'eventTime' => $this->formatTimeLabel((string) ($event['start_time'] ?? '')),
            'headerImageUrl' => trim((string) ($event['image_url'] ?? '')),
            'transactionId' => $transactionId,
        ];

        $this->queueScheduledEvent('event_reminder_24h_image', $transaction, $event, $this->offsetDateTime($eventStartAt, '-24 hours'), $baseContext);
        $this->queueScheduledEvent('event_reminder_6h_image', $transaction, $event, $this->offsetDateTime($eventStartAt, '-6 hours'), $baseContext);
        $this->queueScheduledEvent('event_reminder_2h_image', $transaction, $event, $this->offsetDateTime($eventStartAt, '-2 hours'), $baseContext);
        $this->queueScheduledEvent('event_checkin_pending_30m', $transaction, $event, $this->offsetDateTime($eventStartAt, '+30 minutes'), $baseContext);
    }

    public function scheduleCheckinFollowUp(array $transaction, array $context): void
    {
        $transactionId = trim((string) ($transaction['transaction_id'] ?? ''));
        if ($transactionId === '') {
            return;
        }

        $this->scheduled->cancelPendingByTransaction($transactionId, ['event_checkin_pending_30m'], 'Guest checked in before reminder processing.');
        $checkedInAt = trim((string) ($context['checkedInAt'] ?? ''));
        $dueAt = $checkedInAt !== '' ? $this->offsetDateTime($checkedInAt, '+12 hours') : null;
        if ($dueAt === null) {
            return;
        }

        $this->queueScheduledEvent('event_checkin_thank_you_12h', $transaction, ['event_id' => (string) ($transaction['event_id'] ?? ''), 'title' => (string) ($transaction['event_title'] ?? '')], $dueAt, [
            'customerName' => (string) ($transaction['customer_name'] ?? 'Guest'),
            'eventTitle' => (string) (($transaction['event_title'] ?? '') ?: 'Namaste Kalyan Event'),
        ]);
    }

    public function runScheduler(string $requestedBy, int $limit = 50): array
    {
        $rows = $this->scheduled->listDue(date('Y-m-d H:i:s'), $limit);
        $processed = 0;
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $processed++;
            $eventKey = trim((string) ($row['event_key'] ?? ''));
            $transactionId = trim((string) ($row['transaction_id'] ?? ''));
            $transaction = $transactionId !== '' ? ($this->transactions->findByTransactionId($transactionId) ?: null) : null;
            $context = json_decode((string) ($row['payload_json'] ?? '[]'), true);
            $context = is_array($context) ? $context : [];

            if ($eventKey === 'event_checkin_pending_30m' && $transaction && strtolower(trim((string) ($transaction['checkin_status'] ?? ''))) === 'checked_in') {
                $this->scheduled->markFailed((int) ($row['id'] ?? 0), 'cancelled', 'CHECKIN_ALREADY_DONE', 'Guest already checked in.');
                $skipped++;
                continue;
            }

            $result = $this->triggerEvent($eventKey, (string) ($row['phone'] ?? ''), $context, ['countryCode' => '91', 'leadId' => isset($row['lead_id']) ? (int) $row['lead_id'] : null]);
            if (!empty($result['ok'])) {
                $this->scheduled->markSent((int) ($row['id'] ?? 0), (string) ($result['code'] ?? ''), (string) ($result['message'] ?? 'Sent'));
                $sent++;
                continue;
            }

            $status = !empty($result['attempted']) ? 'failed' : 'skipped';
            $this->scheduled->markFailed((int) ($row['id'] ?? 0), $status, (string) ($result['code'] ?? ''), (string) ($result['message'] ?? 'Failed'));
            if ($status === 'failed') {
                $failed++;
            } else {
                $skipped++;
            }
        }

        return ['ok' => true, 'message' => 'WhatsApp scheduler run completed.', 'requestedBy' => $requestedBy, 'processed' => $processed, 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    public function sendTestMessage(string $eventKey, string $phone, array $context = []): array
    {
        return $this->triggerEvent($eventKey, $phone, $context, ['countryCode' => '91', 'leadId' => null]);
    }

    public function triggerEvent(string $eventKey, string $phone, array $context = [], array $options = []): array
    {
        $phoneDigits = $this->normalizePhoneForMeta($phone, (string) ($options['countryCode'] ?? '91'));
        $mapping = $this->mappings->findByEventKey($eventKey);
        $leadId = isset($options['leadId']) ? (int) $options['leadId'] : null;
        if ($phoneDigits === '') {
            return $this->logSkipped($eventKey, $phone, $leadId, 'INVALID_PHONE', 'Valid WhatsApp phone number is required.');
        }
        if (!$mapping || empty($mapping['is_enabled'])) {
            return $this->logSkipped($eventKey, $phoneDigits, $leadId, 'MAPPING_DISABLED', 'No enabled WhatsApp mapping exists for this event.');
        }
        if (!$this->isReadyForSend()) {
            return $this->logSkipped($eventKey, $phoneDigits, $leadId, 'WHATSAPP_NOT_CONFIGURED', 'Meta access token and phone number ID are required before sending.');
        }

        $templateName = trim((string) ($mapping['template_name'] ?? ''));
        $languageCode = trim((string) ($mapping['language_code'] ?? ''));
        $template = $this->templates->findByNameAndLanguage($templateName, $languageCode);
        if (!$template) {
            return $this->logSkipped($eventKey, $phoneDigits, $leadId, 'TEMPLATE_NOT_FOUND', 'Mapped template is not present in the local template registry.');
        }

        $payload = ['messaging_product' => 'whatsapp', 'to' => $phoneDigits, 'type' => 'template', 'template' => ['name' => $templateName, 'language' => ['code' => $languageCode]]];
        $components = $this->buildTemplateComponents($eventKey, $template, $context);
        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $response = $this->executeJsonRequest('POST', 'https://graph.facebook.com/' . self::API_VERSION . '/' . rawurlencode($this->config['WHATSAPP_META_PHONE_NUMBER_ID']) . '/messages', $payload);
        $message = trim((string) ($response['message'] ?? ''));
        $providerMessageId = $this->extractProviderMessageId($response['data'] ?? []);
        $deliveryStatus = !empty($response['ok']) ? 'accepted' : 'failed';
        $this->logs->create([
            'lead_id' => $leadId,
            'event_key' => $eventKey,
            'phone' => $phoneDigits,
            'template_name' => $templateName,
            'language_code' => $languageCode,
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : null,
            'delivery_status' => $deliveryStatus,
            'status_updated_at' => date('Y-m-d H:i:s'),
            'attempted' => true,
            'success' => !empty($response['ok']),
            'http_code' => (string) ($response['statusCode'] ?? ''),
            'response_message' => $message,
            'request_payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'response_payload_json' => json_encode($response['data'] ?? [], JSON_UNESCAPED_SLASHES),
        ]);

        return ['ok' => !empty($response['ok']), 'attempted' => true, 'success' => !empty($response['ok']), 'status' => (string) ($response['statusCode'] ?? ''), 'code' => (string) ($response['statusCode'] ?? ''), 'message' => $message, 'templateName' => $templateName, 'languageCode' => $languageCode, 'providerMessageId' => $providerMessageId, 'deliveryStatus' => $deliveryStatus];
    }

    public function verifyWebhookChallenge(array $query): array
    {
        $mode = trim((string) ($query['hub_mode'] ?? $query['hub.mode'] ?? ''));
        $token = trim((string) ($query['hub_verify_token'] ?? $query['hub.verify_token'] ?? ''));
        $challenge = trim((string) ($query['hub_challenge'] ?? $query['hub.challenge'] ?? ''));
        if ($mode !== 'subscribe' || $challenge === '') {
            return ['ok' => false, 'error' => 'INVALID_WEBHOOK_CHALLENGE', 'message' => 'Invalid WhatsApp webhook verification request.'];
        }
        if ($token === '' || $token !== $this->config['WHATSAPP_META_VERIFY_TOKEN']) {
            return ['ok' => false, 'error' => 'WHATSAPP_VERIFY_TOKEN_MISMATCH', 'message' => 'Webhook verify token mismatch.'];
        }
        return ['ok' => true, 'challenge' => $challenge];
    }

    public function processWebhookPayload(array $payload): array
    {
        $statuses = $this->extractWebhookStatuses($payload);
        $processed = 0;
        foreach ($statuses as $status) {
            $providerMessageId = trim((string) ($status['id'] ?? ''));
            if ($providerMessageId === '') {
                continue;
            }
            $log = $this->logs->findLatestByProviderMessageId($providerMessageId);
            if (!$log) {
                continue;
            }
            $deliveryStatus = trim((string) ($status['status'] ?? ''));
            $timestamp = trim((string) ($status['timestamp'] ?? ''));
            $statusUpdatedAt = ctype_digit($timestamp) ? date('Y-m-d H:i:s', (int) $timestamp) : date('Y-m-d H:i:s');
            $this->logs->updateDeliveryStatus((int) ($log['id'] ?? 0), $deliveryStatus, $this->buildWebhookStatusMessage($status), $statusUpdatedAt);
            $processed++;
        }
        return ['ok' => true, 'processed' => $processed, 'message' => 'WhatsApp webhook processed.'];
    }

    private function loadConfig(): array
    {
        $stored = $this->apiSettings->getValues(self::CONFIG_KEYS);
        $config = [];
        foreach (self::CONFIG_KEYS as $key) {
            $value = trim((string) ($stored[$key] ?? ''));
            if ($value === '') {
                $value = trim((string) ($_ENV[$key] ?? ''));
            }
            $config[$key] = $value;
        }
        return $config;
    }

    private function isReadyForTemplateSync(): bool
    {
        return $this->config['WHATSAPP_META_ACCESS_TOKEN'] !== '' && $this->config['WHATSAPP_META_BUSINESS_ACCOUNT_ID'] !== '';
    }

    private function isReadyForSend(): bool
    {
        return $this->config['WHATSAPP_META_ACCESS_TOKEN'] !== '' && $this->config['WHATSAPP_META_PHONE_NUMBER_ID'] !== '';
    }

    private function isKnownEvent(string $eventKey): bool
    {
        foreach (self::EVENT_CATALOG as $event) {
            if ($event['eventKey'] === $eventKey) {
                return true;
            }
        }
        return false;
    }

    private function refreshDraftStatusesFromTemplates(array $templates): void
    {
        foreach ($templates as $template) {
            $draft = $this->drafts->findByNameAndLanguage((string) ($template['template_name'] ?? ''), (string) ($template['language_code'] ?? ''));
            if (!$draft) {
                continue;
            }
            $this->drafts->update((int) ($draft['id'] ?? 0), ['draft_name' => (string) ($draft['draft_name'] ?? ''), 'template_name' => (string) ($draft['template_name'] ?? ''), 'category' => (string) ($draft['category'] ?? 'UTILITY'), 'language_code' => (string) ($draft['language_code'] ?? 'en'), 'header_type' => (string) ($draft['header_type'] ?? 'NONE'), 'header_text' => (string) ($draft['header_text'] ?? ''), 'body_text' => (string) ($draft['body_text'] ?? ''), 'footer_text' => (string) ($draft['footer_text'] ?? ''), 'buttons_json' => $draft['buttons_json'] ?? null, 'sample_variables_json' => $draft['sample_variables_json'] ?? null, 'example_media_handle' => (string) ($draft['example_media_handle'] ?? ''), 'status' => strtolower(trim((string) ($template['status'] ?? ''))), 'meta_template_id' => (string) ($template['template_uid'] ?? ''), 'submitted_at' => $draft['submitted_at'] ?? null, 'last_synced_at' => date('Y-m-d H:i:s'), 'rejection_reason' => $this->resolveRejectionReason((string) ($template['status'] ?? ''), (string) ($draft['rejection_reason'] ?? '')), 'updated_by' => (string) ($draft['updated_by'] ?? '')]);
        }
    }

    private function queueScheduledEvent(string $eventKey, array $transaction, array $event, ?string $dueAt, array $context): void
    {
        if ($dueAt === null) {
            return;
        }
        $dueTs = strtotime($dueAt);
        if ($dueTs === false || $dueTs <= time()) {
            return;
        }
        $this->scheduled->upsertByEventAndTransaction(['event_key' => $eventKey, 'transaction_id' => (string) ($transaction['transaction_id'] ?? ''), 'lead_id' => null, 'phone' => (string) ($transaction['customer_phone'] ?? ''), 'customer_name' => (string) ($transaction['customer_name'] ?? ''), 'event_id' => (string) (($event['event_id'] ?? '') ?: ($transaction['event_id'] ?? '')), 'event_title' => (string) (($event['title'] ?? '') ?: ($transaction['event_title'] ?? '')), 'due_at' => $dueAt, 'status' => 'pending', 'payload_json' => json_encode($context, JSON_UNESCAPED_SLASHES), 'last_result_code' => '', 'last_result_message' => '']);
    }

    private function buildTemplateComponents(string $eventKey, array $template, array $context): array
    {
        $components = json_decode((string) ($template['components_json'] ?? '[]'), true);
        if (!is_array($components)) {
            return [];
        }
        $resolved = [];
        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }
            $type = strtolower(trim((string) ($component['type'] ?? '')));
            if ($type === 'body') {
                $parameters = $this->buildBodyParameters($eventKey, $component, $context);
                if ($parameters !== []) {
                    $resolved[] = ['type' => 'body', 'parameters' => $parameters];
                }
                continue;
            }
            if ($type === 'header') {
                $parameters = $this->buildHeaderParameters($component, $context);
                if ($parameters !== []) {
                    $resolved[] = ['type' => 'header', 'parameters' => $parameters];
                }
            }
        }
        return $resolved;
    }

    private function buildBodyParameters(string $eventKey, array $component, array $context): array
    {
        $bodyText = (string) ($component['text'] ?? '');
        preg_match_all('/\{\{\d+\}\}/', $bodyText, $matches);
        $placeholderCount = count($matches[0] ?? []);
        if ($placeholderCount <= 0) {
            return [];
        }
        $variables = $this->eventVariables($eventKey, $context);
        $values = array_slice($variables, 0, $placeholderCount);
        while (count($values) < $placeholderCount) {
            $values[] = '';
        }
        return array_map(static fn(string $value): array => ['type' => 'text', 'text' => $value], $values);
    }

    private function buildHeaderParameters(array $component, array $context): array
    {
        $format = strtoupper(trim((string) ($component['format'] ?? 'TEXT')));
        if ($format === 'TEXT') {
            $text = trim((string) ($context['headerText'] ?? ''));
            return $text === '' ? [] : [['type' => 'text', 'text' => $text]];
        }
        if ($format === 'IMAGE') {
            $link = trim((string) ($context['headerImageUrl'] ?? ''));
            return $link === '' ? [] : [['type' => 'image', 'image' => ['link' => $link]]];
        }
        return [];
    }

    private function eventVariables(string $eventKey, array $context): array
    {
        return match ($eventKey) {
            'event_registration_confirmed' => [trim((string) ($context['customerName'] ?? $context['name'] ?? 'Guest')), trim((string) ($context['eventTitle'] ?? 'Namaste Kalyan Event')), trim((string) ($context['eventDate'] ?? '')), trim((string) ($context['eventTime'] ?? '')), trim((string) ($context['transactionId'] ?? '')), trim((string) ($context['bookingType'] ?? 'Confirmed'))],
            'winner_coupon_issued', 'try_again_surprise_issued', 'coupon_redeemed' => [trim((string) ($context['customerName'] ?? $context['name'] ?? 'Guest')), trim((string) ($context['rewardLabel'] ?? $context['prize'] ?? 'Reward')), trim((string) ($context['couponCode'] ?? ''))],
            'guest_checked_in' => [trim((string) ($context['customerName'] ?? $context['name'] ?? 'Guest')), trim((string) ($context['eventTitle'] ?? 'Namaste Kalyan Event')), trim((string) ($context['admittedCount'] ?? '1')), trim((string) ($context['checkedInAt'] ?? ''))],
            'event_reminder_24h_image', 'event_reminder_6h_image', 'event_reminder_2h_image' => [trim((string) ($context['customerName'] ?? $context['name'] ?? 'Guest')), trim((string) ($context['eventTitle'] ?? 'Namaste Kalyan Event')), trim((string) ($context['eventDate'] ?? '')), trim((string) ($context['eventTime'] ?? ''))],
            'event_checkin_pending_30m' => [trim((string) ($context['customerName'] ?? $context['name'] ?? 'Guest')), trim((string) ($context['eventTitle'] ?? 'Namaste Kalyan Event')), trim((string) ($context['eventTime'] ?? ''))],
            'event_checkin_thank_you_12h' => [trim((string) ($context['customerName'] ?? $context['name'] ?? 'Guest')), trim((string) ($context['eventTitle'] ?? 'Namaste Kalyan Event'))],
            default => [],
        };
    }

    private function buildCreateTemplatePayload(array $draft): array
    {
        $components = [];
        $headerType = strtoupper(trim((string) ($draft['header_type'] ?? 'NONE')));
        if ($headerType === 'TEXT' && trim((string) ($draft['header_text'] ?? '')) !== '') {
            $components[] = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => (string) ($draft['header_text'] ?? ''), 'example' => ['header_text' => [(string) ($draft['header_text'] ?? '')]]];
        } elseif ($headerType === 'IMAGE') {
            $handle = trim((string) ($draft['example_media_handle'] ?? ''));
            $components[] = ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => $handle !== '' ? ['header_handle' => [$handle]] : new \stdClass()];
        }
        $components[] = ['type' => 'BODY', 'text' => (string) ($draft['body_text'] ?? ''), 'example' => $this->buildBodyExampleFromDraft($draft)];
        if (trim((string) ($draft['footer_text'] ?? '')) !== '') {
            $components[] = ['type' => 'FOOTER', 'text' => (string) ($draft['footer_text'] ?? '')];
        }

        $buttons = json_decode((string) ($draft['buttons_json'] ?? '[]'), true);
        if (is_array($buttons) && $buttons !== []) {
            $buttonComponents = [];
            foreach ($buttons as $button) {
                if (!is_array($button)) {
                    continue;
                }
                $type = strtoupper(trim((string) ($button['type'] ?? '')));
                $text = trim((string) ($button['text'] ?? ''));
                if ($type === '' || $text === '') {
                    continue;
                }
                $entry = ['type' => $type, 'text' => $text];
                $url = trim((string) ($button['url'] ?? ''));
                $phoneNumber = trim((string) ($button['phoneNumber'] ?? $button['phone_number'] ?? ''));
                if ($type === 'URL' && $url !== '') {
                    $entry['url'] = $url;
                }
                if ($type === 'PHONE_NUMBER' && $phoneNumber !== '') {
                    $entry['phone_number'] = $phoneNumber;
                }
                $buttonComponents[] = $entry;
            }
            if ($buttonComponents !== []) {
                $components[] = ['type' => 'BUTTONS', 'buttons' => $buttonComponents];
            }
        }

        return ['name' => (string) ($draft['template_name'] ?? ''), 'language' => (string) ($draft['language_code'] ?? 'en'), 'category' => (string) ($draft['category'] ?? 'UTILITY'), 'components' => $components];
    }

    private function buildBodyExampleFromDraft(array $draft): object|array
    {
        $samples = json_decode((string) ($draft['sample_variables_json'] ?? '[]'), true);
        $samples = is_array($samples) ? array_values(array_filter(array_map(static fn($value): string => trim((string) $value), $samples))) : [];
        return $samples === [] ? new \stdClass() : ['body_text' => [$samples]];
    }

    private function normalizePhoneForMeta(string $phone, string $countryCode = '91'): string
    {
        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
        $countryDigits = preg_replace('/\D+/', '', $countryCode) ?? '';
        if ($phoneDigits === '') {
            return '';
        }
        if (strlen($phoneDigits) === 10) {
            $phoneDigits = ($countryDigits !== '' ? $countryDigits : '91') . $phoneDigits;
        }
        return ltrim($phoneDigits, '0');
    }

    private function executeJsonRequest(string $method, string $url, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'statusCode' => '', 'message' => 'cURL extension is not available.', 'data' => []];
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'statusCode' => '', 'message' => 'Unable to initialize cURL.', 'data' => []];
        }

        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $this->config['WHATSAPP_META_ACCESS_TOKEN']];
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $headers];
        if (strtoupper($method) === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_SLASHES);
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);
        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($curlError !== '') {
            return ['ok' => false, 'statusCode' => '', 'message' => substr($curlError, 0, 500), 'data' => []];
        }
        $decoded = json_decode((string) $responseBody, true);
        $decoded = is_array($decoded) ? $decoded : [];
        return ['ok' => $statusCode >= 200 && $statusCode < 300, 'statusCode' => $statusCode > 0 ? (string) $statusCode : '', 'message' => $this->extractResponseMessage($decoded, $statusCode), 'data' => $decoded];
    }

    private function extractResponseMessage(array $payload, int $statusCode): string
    {
        if (isset($payload['error']) && is_array($payload['error'])) {
            return substr((string) ($payload['error']['message'] ?? 'WhatsApp API error'), 0, 500);
        }
        if (isset($payload['messages'][0]['id'])) {
            return 'WhatsApp message accepted: ' . (string) $payload['messages'][0]['id'];
        }
        if (isset($payload['id'])) {
            return 'WhatsApp template submitted: ' . (string) $payload['id'];
        }
        if (isset($payload['data']) && is_array($payload['data'])) {
            return 'WhatsApp template sync completed with HTTP ' . $statusCode . '.';
        }
        return 'WhatsApp API request completed with HTTP ' . $statusCode . '.';
    }

    private function logSkipped(string $eventKey, string $phone, ?int $leadId, string $code, string $message): array
    {
        $this->logs->create(['lead_id' => $leadId, 'event_key' => $eventKey, 'phone' => $phone, 'template_name' => '', 'language_code' => '', 'provider_message_id' => null, 'delivery_status' => 'skipped', 'status_updated_at' => date('Y-m-d H:i:s'), 'attempted' => false, 'success' => false, 'http_code' => $code, 'response_message' => $message, 'request_payload_json' => null, 'response_payload_json' => null]);
        return ['ok' => false, 'attempted' => false, 'success' => false, 'status' => '', 'code' => $code, 'message' => $message];
    }

    private function formatTemplateForWorkspace(array $template): array
    {
        return ['id' => (int) ($template['id'] ?? 0), 'templateUid' => (string) ($template['template_uid'] ?? ''), 'templateName' => (string) ($template['template_name'] ?? ''), 'languageCode' => (string) ($template['language_code'] ?? ''), 'category' => (string) ($template['category'] ?? ''), 'status' => (string) ($template['status'] ?? ''), 'qualityScore' => (string) ($template['quality_score'] ?? ''), 'lastSyncedAt' => (string) ($template['last_synced_at'] ?? '')];
    }

    private function formatLogForWorkspace(array $log): array
    {
        return ['id' => (int) ($log['id'] ?? 0), 'createdAt' => (string) ($log['created_at'] ?? ''), 'leadId' => isset($log['lead_id']) ? (int) $log['lead_id'] : 0, 'eventKey' => (string) ($log['event_key'] ?? ''), 'phone' => (string) ($log['phone'] ?? ''), 'templateName' => (string) ($log['template_name'] ?? ''), 'languageCode' => (string) ($log['language_code'] ?? ''), 'providerMessageId' => (string) ($log['provider_message_id'] ?? ''), 'deliveryStatus' => (string) ($log['delivery_status'] ?? ''), 'statusUpdatedAt' => (string) ($log['status_updated_at'] ?? ''), 'attempted' => !empty($log['attempted']), 'success' => !empty($log['success']), 'httpCode' => (string) ($log['http_code'] ?? ''), 'responseMessage' => (string) ($log['response_message'] ?? '')];
    }

    private function formatDraftForWorkspace(array $draft): array
    {
        return ['id' => (int) ($draft['id'] ?? 0), 'draftName' => (string) ($draft['draft_name'] ?? ''), 'templateName' => (string) ($draft['template_name'] ?? ''), 'category' => (string) ($draft['category'] ?? ''), 'languageCode' => (string) ($draft['language_code'] ?? ''), 'headerType' => (string) ($draft['header_type'] ?? ''), 'headerText' => (string) ($draft['header_text'] ?? ''), 'bodyText' => (string) ($draft['body_text'] ?? ''), 'footerText' => (string) ($draft['footer_text'] ?? ''), 'buttons' => json_decode((string) ($draft['buttons_json'] ?? '[]'), true) ?: [], 'sampleVariables' => json_decode((string) ($draft['sample_variables_json'] ?? '[]'), true) ?: [], 'exampleMediaHandle' => (string) ($draft['example_media_handle'] ?? ''), 'status' => (string) ($draft['status'] ?? ''), 'metaTemplateId' => (string) ($draft['meta_template_id'] ?? ''), 'submittedAt' => (string) ($draft['submitted_at'] ?? ''), 'lastSyncedAt' => (string) ($draft['last_synced_at'] ?? ''), 'rejectionReason' => (string) ($draft['rejection_reason'] ?? ''), 'updatedAt' => (string) ($draft['updated_at'] ?? '')];
    }

    private function formatScheduleForWorkspace(array $row): array
    {
        return ['id' => (int) ($row['id'] ?? 0), 'eventKey' => (string) ($row['event_key'] ?? ''), 'transactionId' => (string) ($row['transaction_id'] ?? ''), 'customerName' => (string) ($row['customer_name'] ?? ''), 'phone' => (string) ($row['phone'] ?? ''), 'eventTitle' => (string) ($row['event_title'] ?? ''), 'dueAt' => (string) ($row['due_at'] ?? ''), 'status' => (string) ($row['status'] ?? ''), 'attemptCount' => (int) ($row['attempt_count'] ?? 0), 'lastResultCode' => (string) ($row['last_result_code'] ?? ''), 'lastResultMessage' => (string) ($row['last_result_message'] ?? ''), 'sentAt' => (string) ($row['sent_at'] ?? '')];
    }

    private function extractProviderMessageId(array $payload): string
    {
        return isset($payload['messages'][0]['id']) ? trim((string) $payload['messages'][0]['id']) : '';
    }

    private function extractWebhookStatuses(array $payload): array
    {
        $statuses = [];
        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];
        foreach ($entries as $entry) {
            $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $changeStatuses = is_array($value['statuses'] ?? null) ? $value['statuses'] : [];
                foreach ($changeStatuses as $status) {
                    if (is_array($status)) {
                        $statuses[] = $status;
                    }
                }
            }
        }
        return $statuses;
    }

    private function buildWebhookStatusMessage(array $status): string
    {
        $state = trim((string) ($status['status'] ?? ''));
        $recipient = trim((string) ($status['recipient_id'] ?? ''));
        $errors = is_array($status['errors'] ?? null) ? $status['errors'] : [];
        $errorMessage = $errors !== [] && is_array($errors[0] ?? null) ? trim((string) (($errors[0]['title'] ?? '') ?: ($errors[0]['message'] ?? ''))) : '';
        $message = 'Meta status: ' . ($state !== '' ? $state : 'unknown');
        if ($recipient !== '') {
            $message .= ' for ' . $recipient;
        }
        if ($errorMessage !== '') {
            $message .= ' - ' . $errorMessage;
        }
        return substr($message, 0, 500);
    }

    private function buildEventStartAt(string $date, string $time): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }
        $ts = strtotime(trim($date . ' ' . ($time !== '' ? $time : '00:00:00')));
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    private function offsetDateTime(string $dateTime, string $offset): ?string
    {
        $ts = strtotime($dateTime . ' ' . $offset);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    private function formatDateLabel(string $date): string
    {
        $ts = strtotime($date);
        return $ts === false ? trim($date) : date('d M Y', $ts);
    }

    private function formatTimeLabel(string $time): string
    {
        $ts = strtotime($time);
        return $ts === false ? trim($time) : date('g:i A', $ts);
    }

    private function sanitizeTemplateName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        return trim($value, '_');
    }

    private function normalizeTemplateCategory(string $value): string
    {
        $value = strtoupper(trim($value));
        return in_array($value, ['MARKETING', 'UTILITY'], true) ? $value : 'UTILITY';
    }

    private function normalizeHeaderType(string $value): string
    {
        $value = strtoupper(trim($value));
        return in_array($value, ['NONE', 'TEXT', 'IMAGE'], true) ? $value : 'NONE';
    }

    private function resolveRejectionReason(string $templateStatus, string $existingReason): string
    {
        $status = strtolower(trim($templateStatus));
        if ($status === 'rejected') {
            return $existingReason !== '' ? $existingReason : 'Template rejected by Meta. Review wording, category, and examples.';
        }
        if ($status === 'approved') {
            return '';
        }
        return $existingReason;
    }
}