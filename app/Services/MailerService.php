<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Support\Logger;
use NK\Support\SiteUrl;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MailerService
{
    public function smtpHealthCheck(): array
    {
        $host = $this->env('SMTP_HOST');
        $port = $this->env('SMTP_PORT', '465');
        $secure = strtolower($this->env('SMTP_SECURE', 'ssl'));
        $user = $this->env('SMTP_USER');

        $response = [
            'ok' => false,
            'configured' => $this->isConfigured(),
            'smtp' => [
                'host' => $host,
                'port' => (int) $port,
                'secure' => $secure,
                'userMasked' => $this->maskEmail($user),
            ],
            'checks' => [
                'smtpHostSet' => $host !== '',
                'smtpPortSet' => trim((string) $port) !== '',
                'smtpUserSet' => $user !== '',
                'smtpPassSet' => $this->env('SMTP_PASS') !== '',
            ],
        ];

        if (!$response['configured']) {
            $response['error'] = 'MAILER_NOT_CONFIGURED';
            $response['message'] = 'SMTP configuration is incomplete.';
            return $response;
        }

        try {
            $mailer = $this->createMailer();
            // Open and close SMTP transport without sending an actual email.
            $connected = $mailer->smtpConnect();
            if ($connected !== true) {
                $response['error'] = 'SMTP_CONNECT_FAILED';
                $response['message'] = 'SMTP server connection failed.';
                return $response;
            }

            $mailer->smtpClose();

            $response['ok'] = true;
            $response['message'] = 'SMTP connection and authentication succeeded.';
            return $response;
        } catch (\Throwable $exception) {
            $response['error'] = 'SMTP_EXCEPTION';
            $response['message'] = $exception->getMessage();
            return $response;
        }
    }

    public function isConfigured(): bool
    {
        return $this->env('SMTP_HOST') !== ''
            && $this->env('SMTP_PORT') !== ''
            && $this->env('SMTP_USER') !== ''
            && $this->env('SMTP_PASS') !== '';
    }

    public function sendEventOtp(string $email, string $customerName, string $eventTitle, string $otp, int $expiresInMinutes): array
    {
        $subject = 'Verify your email for ' . ($eventTitle !== '' ? $eventTitle : 'your event registration');
        $guestName = $customerName !== '' ? $customerName : 'Guest';
        $safeGuestName = $this->escape($guestName);
        $safeEventTitle = $this->escape($eventTitle !== '' ? $eventTitle : 'Namaste Kalyan Event');
        $safeOtp = $this->escape($otp);
        $logoUrl = $this->assetUrl('assets/Logo/Namaste%20Kalyan%20by%20AWG%20-02.png');

        $html = '
            <div style="margin:0;padding:24px;background:#0f0a0d;font-family:Segoe UI,Arial,sans-serif;color:#f7f2eb;">
              <div style="max-width:620px;margin:0 auto;border:1px solid #3b2527;border-radius:18px;overflow:hidden;background:#181014;">
                <div style="padding:24px;background:linear-gradient(135deg,#320206,#171015 72%,#1d1217);border-bottom:1px solid #53322f;">
                  <img src="' . $logoUrl . '" alt="Namaste Kalyan" style="display:block;height:52px;width:auto;max-width:240px;" />
                </div>
                <div style="padding:28px 24px;">
                  <p style="margin:0 0 12px 0;font-size:16px;color:#f7f2eb;">Hello <strong>' . $safeGuestName . '</strong>,</p>
                  <p style="margin:0 0 18px 0;font-size:15px;line-height:1.7;color:#eadfd3;">Use the OTP below to verify your email address before we continue with your registration for <strong style="color:#f2c48a;">' . $safeEventTitle . '</strong>.</p>
                  <div style="margin:0 0 18px 0;padding:20px;border-radius:16px;background:#241418;border:1px solid #6b4836;text-align:center;">
                    <div style="font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:#c8aa8a;margin-bottom:8px;">Your OTP</div>
                    <div style="font-size:34px;font-weight:700;letter-spacing:10px;color:#ffffff;">' . $safeOtp . '</div>
                    <div style="margin-top:10px;font-size:13px;color:#eadfd3;">This OTP expires in ' . $expiresInMinutes . ' minutes.</div>
                  </div>
                  <p style="margin:0;font-size:13px;line-height:1.7;color:#c8b9ab;">If you did not request this verification, you can ignore this email. For help, contact us at ' . $this->escape($this->supportPhone()) . '.</p>
                </div>
              </div>
            </div>';

        $text = implode("\n", [
            'Hello ' . $guestName . ',',
            '',
            'Use this OTP to verify your email for ' . ($eventTitle !== '' ? $eventTitle : 'your event registration') . ':',
            $otp,
            '',
            'This OTP expires in ' . $expiresInMinutes . ' minutes.',
            'Support: ' . $this->supportPhone(),
        ]);

        return $this->send($email, $subject, $html, $text, [
            'kind' => 'event_otp',
            'eventTitle' => $eventTitle,
        ]);
    }

    public function sendEventConfirmation(array $payload): array
    {
        $email = trim((string) ($payload['customerEmail'] ?? ''));
        if ($email === '') {
            return ['ok' => false, 'error' => 'EMAIL_REQUIRED', 'message' => 'Customer email is required.'];
        }

        $isFreeRegistration = !empty($payload['isFreeRegistration']);
        $eventTitle = trim((string) ($payload['eventTitle'] ?? 'Special Event'));
        $subject = $isFreeRegistration
            ? 'Your Free Event Pass - ' . $eventTitle
            : 'Your Event Pass - ' . $eventTitle;

        $customerName = trim((string) ($payload['customerName'] ?? 'Guest'));
        $eventSubtitle = trim((string) ($payload['eventSubtitle'] ?? ''));
        $transactionId = trim((string) ($payload['transactionId'] ?? '-'));
        $qty = max(1, (int) ($payload['qty'] ?? 1));
        $amount = (float) ($payload['amount'] ?? 0);
        $currency = strtoupper(trim((string) ($payload['currency'] ?? 'INR')) ?: 'INR');
        $verificationUrl = $this->absolutizeUrl((string) ($payload['verificationUrl'] ?? ''));
        $qrUrl = $this->absolutizeUrl((string) ($payload['qrUrl'] ?? ''));
        $eventImageUrl = $this->absolutizeUrl((string) ($payload['eventImageUrl'] ?? ''));
        $policyText = trim((string) ($payload['policyText'] ?? ($isFreeRegistration ? 'Free entry registration confirmed.' : 'No refund once pass is purchased.')));
        $attendeeNames = $payload['attendeeNames'] ?? [];
        if (!is_array($attendeeNames)) {
            $attendeeNames = [];
        }

        $attendeeItems = '';
        foreach ($attendeeNames as $attendeeName) {
            $value = trim((string) $attendeeName);
            if ($value === '') {
                continue;
            }
            $attendeeItems .= '<li style="margin:0 0 4px 0;">' . $this->escape($value) . '</li>';
        }

        $logoUrl = $this->assetUrl('assets/Logo/Namaste%20Kalyan%20by%20AWG%20-02.png');
        $amountLabel = $isFreeRegistration ? 'Entry Type' : 'Amount Paid';
        $amountValue = $isFreeRegistration ? 'Free Entry' : ($currency . ' ' . number_format($amount, 2));
        $confirmationLabel = $isFreeRegistration ? 'Registration Confirmed At' : 'Payment Confirmed At';
        $summaryLine = $qty > 1
            ? $qty . ' guest passes have been prepared for this booking.'
            : 'Your event pass is ready.';

        $html = '
          <html>
            <body style="margin:0;padding:0;background:#080506;font-family:Segoe UI,Arial,sans-serif;color:#f7f2eb;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#080506;padding:20px 10px;">
                <tr>
                  <td align="center">
                    <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#130d10;border-radius:16px;overflow:hidden;border:1px solid #3b2527;">
                      <tr>
                        <td style="padding:24px;background:linear-gradient(135deg,#330003,#130d10 70%,#1b0e12);color:#f7f2eb;border-bottom:1px solid #5c3f20;">
                          <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                              <td style="vertical-align:middle;padding:0 12px 0 0;"><img src="' . $logoUrl . '" alt="Namaste Kalyan" style="display:block;height:56px;width:auto;max-width:230px;" /></td>
                              <td style="vertical-align:middle;text-align:right;">
                                <p style="margin:0;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:#f2c48a;">' . $this->escape($isFreeRegistration ? 'Free Registration Confirmed' : 'Booking Confirmed') . '</p>
                                <h1 style="margin:6px 0 0 0;font-size:22px;line-height:1.2;color:#f7f2eb;">Event Ticket</h1>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:24px;">
                          ' . ($eventImageUrl !== '' ? '<div style="margin:0 0 18px 0;overflow:hidden;border-radius:14px;border:1px solid #402726;background:#120d0f;"><img src="' . $this->escape($eventImageUrl) . '" alt="' . $this->escape($eventTitle) . '" style="display:block;width:100%;max-height:260px;object-fit:cover;" /></div>' : '') . '
                          <p style="margin:0 0 12px 0;font-size:16px;color:#f7f2eb;">Hello <strong>' . $this->escape($customerName) . '</strong>,</p>
                          <p style="margin:0 0 18px 0;font-size:15px;line-height:1.6;color:#eadfd3;">' . $this->escape($isFreeRegistration ? 'Thanks for registering with us. Your free entry pass is confirmed for ' : 'Thanks for booking with us. Your pass is confirmed for ') . '<strong style="color:#f2c48a;">' . $this->escape($eventTitle) . '</strong>.</p>
                          ' . ($eventSubtitle !== '' ? '<p style="margin:0 0 16px 0;font-size:13px;color:#d2c4b8;">' . $this->escape($eventSubtitle) . '</p>' : '') . '
                          <p style="margin:0 0 18px 0;font-size:14px;color:#d2c4b8;">' . $this->escape($summaryLine) . '</p>
                          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #3b2527;border-radius:10px;overflow:hidden;margin-bottom:18px;background:#1b0e12;">
                            <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">' . $this->escape($isFreeRegistration ? 'Registration ID' : 'Transaction ID') . '</td><td style="padding:12px 14px;font-size:14px;font-weight:600;color:#f7f2eb;">' . $this->escape($transactionId) . '</td></tr>
                            <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Registered At</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">' . $this->escape($this->formatDateTime((string) ($payload['createdAt'] ?? ''))) . '</td></tr>
                            <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">' . $this->escape($confirmationLabel) . '</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">' . $this->escape($this->formatDateTime((string) ($payload['paidAt'] ?? ''))) . '</td></tr>
                            <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Event Starts</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">' . $this->escape($this->formatDateTime((string) ($payload['eventStartAt'] ?? ''))) . '</td></tr>
                            <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Event Ends</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">' . $this->escape($this->formatDateTime((string) ($payload['eventEndAt'] ?? ''))) . '</td></tr>
                            <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Tickets</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">' . $qty . '</td></tr>
                            <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">' . $this->escape($amountLabel) . '</td><td style="padding:12px 14px;font-size:14px;color:#f2c48a;">' . $this->escape($amountValue) . '</td></tr>
                          </table>
                          ' . ($attendeeItems !== '' ? '<div style="margin:0 0 16px 0;padding:10px 12px;background:#261116;border:1px solid #5c3f20;border-radius:10px;"><p style="margin:0 0 8px 0;font-size:13px;color:#f2c48a;"><strong>Attendees</strong></p><ul style="margin:0;padding-left:18px;font-size:13px;color:#eadfd3;">' . $attendeeItems . '</ul></div>' : '') . '
                          <p style="margin:0 0 8px 0;font-size:14px;color:#eadfd3;"><strong>Venue:</strong> ' . $this->escape($this->venueAddress()) . '</p>
                          <p style="margin:0 0 18px 0;font-size:14px;color:#eadfd3;"><strong>Support:</strong> ' . $this->escape($this->supportPhone()) . ' | <strong>Policy:</strong> ' . $this->escape($policyText) . '</p>
                          ' . ($qrUrl !== '' ? '<div style="text-align:center;padding:14px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;"><p style="margin:0 0 10px 0;font-size:13px;color:#475569;">Show this QR code at entry</p><img src="' . $this->escape($qrUrl) . '" alt="Event QR" style="width:240px;height:240px;border-radius:10px;border:1px solid #d1d5db;background:#ffffff;" />' . ($verificationUrl !== '' ? '<p style="margin:10px 0 0 0;font-size:12px;word-break:break-all;"><a href="' . $this->escape($verificationUrl) . '" style="color:#1d4ed8;text-decoration:none;">' . $this->escape($verificationUrl) . '</a></p>' : '') . '</div>' : '') . '
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:16px 24px;background:#1b0e12;border-top:1px solid #3b2527;font-size:12px;color:#d2c4b8;">Please keep this email for your records and show this confirmation at the entry desk if needed.</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </body>
          </html>';

        $textLines = [
            'Hello ' . $customerName . ',',
            $isFreeRegistration
                ? 'Your free registration is confirmed for ' . $eventTitle . '.'
                : 'Your pass is confirmed for ' . $eventTitle . '.',
            'Transaction ID: ' . $transactionId,
            'Tickets: ' . $qty,
            $amountLabel . ': ' . $amountValue,
            'Registered At: ' . $this->formatDateTime((string) ($payload['createdAt'] ?? '')),
            $confirmationLabel . ': ' . $this->formatDateTime((string) ($payload['paidAt'] ?? '')),
            'Event Starts: ' . $this->formatDateTime((string) ($payload['eventStartAt'] ?? '')),
            'Event Ends: ' . $this->formatDateTime((string) ($payload['eventEndAt'] ?? '')),
            'Venue: ' . $this->venueAddress(),
            'Support: ' . $this->supportPhone(),
            'Policy: ' . $policyText,
        ];
        if ($verificationUrl !== '') {
            $textLines[] = 'Verification Link: ' . $verificationUrl;
        }
        if ($qrUrl !== '') {
            $textLines[] = 'QR Link: ' . $qrUrl;
        }

        return $this->send($email, $subject, $html, implode("\n", $textLines), [
            'kind' => 'event_confirmation',
            'transactionId' => $transactionId,
            'eventTitle' => $eventTitle,
            'qty' => $qty,
            'isFreeRegistration' => $isFreeRegistration,
        ]);
    }

        public function sendGuestCheckinConfirmation(array $payload): array
        {
                $email = trim((string) ($payload['customerEmail'] ?? ''));
                if ($email === '') {
                        return ['ok' => false, 'error' => 'EMAIL_REQUIRED', 'message' => 'Customer email is required.'];
                }

                $customerName = trim((string) ($payload['customerName'] ?? 'Guest'));
                $eventTitle = trim((string) ($payload['eventTitle'] ?? 'Namaste Kalyan Event'));
                $eventSubtitle = trim((string) ($payload['eventSubtitle'] ?? ''));
                $transactionId = trim((string) ($payload['transactionId'] ?? '-'));
                $checkedInAt = (string) ($payload['checkedInAt'] ?? '');
                $admittedCount = max(1, (int) ($payload['admittedCount'] ?? 1));
                $remainingEntries = max(0, (int) ($payload['remainingEntries'] ?? 0));
                $bookingType = trim((string) ($payload['bookingType'] ?? 'Event Entry'));
                $eventImageUrl = $this->absolutizeUrl((string) ($payload['eventImageUrl'] ?? ''));
                $verificationUrl = $this->absolutizeUrl((string) ($payload['verificationUrl'] ?? ''));
                $attendeeNames = $payload['attendeeNames'] ?? [];
                if (!is_array($attendeeNames)) {
                        $attendeeNames = [];
                }

                $attendeeItems = '';
                foreach ($attendeeNames as $attendeeName) {
                        $value = trim((string) $attendeeName);
                        if ($value === '') {
                                continue;
                        }
                        $attendeeItems .= '<li style="margin:0 0 4px 0;">' . $this->escape($value) . '</li>';
                }

                $logoUrl = $this->assetUrl('assets/Logo/Namaste%20Kalyan%20by%20AWG%20-02.png');
                $summaryLine = $admittedCount === 1
                        ? 'Your event check-in is complete for 1 guest.'
                        : 'Your event check-in is complete for ' . $admittedCount . ' guests.';
                $nextStepLine = $remainingEntries > 0
                        ? $remainingEntries . ' entr' . ($remainingEntries === 1 ? 'y is' : 'ies are') . ' still available on this booking.'
                        : 'All guests on this booking have now been checked in.';

                $html = '
                    <html>
                        <body style="margin:0;padding:0;background:#080506;font-family:Segoe UI,Arial,sans-serif;color:#f7f2eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#080506;padding:20px 10px;">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#130d10;border-radius:16px;overflow:hidden;border:1px solid #3b2527;">
                                            <tr>
                                                <td style="padding:24px;background:linear-gradient(135deg,#330003,#130d10 70%,#1b0e12);color:#f7f2eb;border-bottom:1px solid #5c3f20;">
                                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                                        <tr>
                                                            <td style="vertical-align:middle;padding:0 12px 0 0;"><img src="' . $logoUrl . '" alt="Namaste Kalyan" style="display:block;height:56px;width:auto;max-width:230px;" /></td>
                                                            <td style="vertical-align:middle;text-align:right;">
                                                                <p style="margin:0;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:#f2c48a;">Entry Confirmed</p>
                                                                <h1 style="margin:6px 0 0 0;font-size:22px;line-height:1.2;color:#f7f2eb;">Check-In Completed</h1>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:24px;">
                                                    ' . ($eventImageUrl !== '' ? '<div style="margin:0 0 18px 0;overflow:hidden;border-radius:14px;border:1px solid #402726;background:#120d0f;"><img src="' . $this->escape($eventImageUrl) . '" alt="' . $this->escape($eventTitle) . '" style="display:block;width:100%;max-height:260px;object-fit:cover;" /></div>' : '') . '
                                                    <p style="margin:0 0 12px 0;font-size:16px;color:#f7f2eb;">Hello <strong>' . $this->escape($customerName) . '</strong>,</p>
                                                    <p style="margin:0 0 12px 0;font-size:15px;line-height:1.6;color:#eadfd3;">' . $this->escape($summaryLine) . ' We have recorded your entry for <strong style="color:#f2c48a;">' . $this->escape($eventTitle) . '</strong>.</p>
                                                    ' . ($eventSubtitle !== '' ? '<p style="margin:0 0 16px 0;font-size:13px;color:#d2c4b8;">' . $this->escape($eventSubtitle) . '</p>' : '') . '
                                                    <p style="margin:0 0 18px 0;font-size:14px;color:#d2c4b8;">' . $this->escape($nextStepLine) . '</p>
                                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #3b2527;border-radius:10px;overflow:hidden;margin-bottom:18px;background:#1b0e12;">
                                                        <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Transaction ID</td><td style="padding:12px 14px;font-size:14px;font-weight:600;color:#f7f2eb;">' . $this->escape($transactionId) . '</td></tr>
                                                        <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Check-In Completed At</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">' . $this->escape($this->formatDateTime($checkedInAt)) . '</td></tr>
                                                        <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Booking Type</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">' . $this->escape($bookingType) . '</td></tr>
                                                        <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Guests Checked In</td><td style="padding:12px 14px;font-size:14px;color:#f2c48a;">' . $admittedCount . '</td></tr>
                                                        <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Remaining Entries</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">' . $remainingEntries . '</td></tr>
                                                    </table>
                                                    ' . ($attendeeItems !== '' ? '<div style="margin:0 0 16px 0;padding:10px 12px;background:#261116;border:1px solid #5c3f20;border-radius:10px;"><p style="margin:0 0 8px 0;font-size:13px;color:#f2c48a;"><strong>Members Checked In</strong></p><ul style="margin:0;padding-left:18px;font-size:13px;color:#eadfd3;">' . $attendeeItems . '</ul></div>' : '') . '
                                                    <p style="margin:0 0 8px 0;font-size:14px;color:#eadfd3;"><strong>Venue:</strong> ' . $this->escape($this->venueAddress()) . '</p>
                                                    <p style="margin:0 0 18px 0;font-size:14px;color:#eadfd3;"><strong>Support:</strong> ' . $this->escape($this->supportPhone()) . '</p>
                                                    ' . ($verificationUrl !== '' ? '<div style="text-align:center;padding:14px;border:1px dashed #5c3f20;border-radius:12px;background:#170f13;"><p style="margin:0 0 10px 0;font-size:13px;color:#d2c4b8;">Need to review this booking later?</p><p style="margin:0;font-size:12px;word-break:break-all;"><a href="' . $this->escape($verificationUrl) . '" style="color:#f2c48a;text-decoration:none;">' . $this->escape($verificationUrl) . '</a></p></div>' : '') . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:16px 24px;background:#1b0e12;border-top:1px solid #3b2527;font-size:12px;color:#d2c4b8;">This is an automated check-in confirmation from Namaste Kalyan. Please keep it for your records.</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </body>
                    </html>';

                $textLines = [
                        'Hello ' . $customerName . ',',
                        'Your check-in has been completed for ' . $eventTitle . '.',
                        'Transaction ID: ' . $transactionId,
                        'Check-In Completed At: ' . $this->formatDateTime($checkedInAt),
                        'Booking Type: ' . $bookingType,
                        'Guests Checked In: ' . $admittedCount,
                        'Remaining Entries: ' . $remainingEntries,
                ];
                if ($attendeeNames !== []) {
                        $textLines[] = 'Members Checked In: ' . implode(', ', array_map(static fn($value): string => trim((string) $value), $attendeeNames));
                }
                $textLines[] = 'Venue: ' . $this->venueAddress();
                $textLines[] = 'Support: ' . $this->supportPhone();
                if ($verificationUrl !== '') {
                        $textLines[] = 'Verification Link: ' . $verificationUrl;
                }

                return $this->send($email, 'Check-In Confirmed - ' . $eventTitle, $html, implode("\n", $textLines), [
                        'kind' => 'event_checkin_confirmation',
                        'transactionId' => $transactionId,
                        'eventTitle' => $eventTitle,
                        'qty' => $admittedCount,
                        'isFreeRegistration' => false,
                ]);
        }

    private function send(string $to, string $subject, string $htmlBody, string $textBody, array $meta = []): array
    {
        if (!$this->isConfigured()) {
            Logger::error('Event email skipped because SMTP is not fully configured.', [
                'kind' => (string) ($meta['kind'] ?? 'unknown'),
                'to' => $this->maskEmail($to),
                'subject' => $subject,
                'smtpHost' => $this->env('SMTP_HOST'),
                'smtpPort' => $this->env('SMTP_PORT', '465'),
                'smtpSecure' => strtolower($this->env('SMTP_SECURE', 'ssl')),
            ]);
            return [
                'ok' => false,
                'error' => 'MAILER_NOT_CONFIGURED',
                'message' => 'SMTP configuration is incomplete. Please update SMTP settings.',
            ];
        }

        try {
            $mailer = $this->createMailer();
            $fromEmail = $this->env('SMTP_USER');
            $fromName = $this->env('SMTP_FROM_NAME', 'Namaste Kalyan');
            $toEmail = trim($to);
            $ccRecipients = $this->ccRecipients();

            Logger::info('Attempting event email handoff.', [
                'kind' => (string) ($meta['kind'] ?? 'unknown'),
                'transactionId' => (string) ($meta['transactionId'] ?? ''),
                'eventTitle' => (string) ($meta['eventTitle'] ?? ''),
                'qty' => isset($meta['qty']) ? (int) $meta['qty'] : null,
                'isFreeRegistration' => isset($meta['isFreeRegistration']) ? (bool) $meta['isFreeRegistration'] : null,
                'to' => $this->maskEmail($toEmail),
                'recipientDomain' => $this->emailDomain($toEmail),
                'ccCount' => count($ccRecipients),
                'from' => $this->maskEmail($fromEmail),
                'subject' => $subject,
                'smtpHost' => $mailer->Host,
                'smtpPort' => $mailer->Port,
                'smtpSecure' => (string) $mailer->SMTPSecure,
            ]);

            $mailer->setFrom($fromEmail, $fromName);
            $mailer->addAddress($toEmail);

            foreach ($ccRecipients as $cc) {
                $mailer->addCC($cc);
            }

            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $textBody;
            $mailer->send();

            Logger::info('Event email handoff accepted by SMTP transport.', [
                'kind' => (string) ($meta['kind'] ?? 'unknown'),
                'transactionId' => (string) ($meta['transactionId'] ?? ''),
                'to' => $this->maskEmail($toEmail),
                'recipientDomain' => $this->emailDomain($toEmail),
                'subject' => $subject,
                'messageId' => (string) $mailer->getLastMessageID(),
                'ccCount' => count($ccRecipients),
                'smtpHost' => $mailer->Host,
                'smtpPort' => $mailer->Port,
                'smtpSecure' => (string) $mailer->SMTPSecure,
            ]);

            return [
                'ok' => true,
                'message' => 'Email sent successfully.',
                'messageId' => (string) $mailer->getLastMessageID(),
            ];
        } catch (Exception $exception) {
            Logger::error('Event email sending failed.', [
                'kind' => (string) ($meta['kind'] ?? 'unknown'),
                'transactionId' => (string) ($meta['transactionId'] ?? ''),
                'to' => $this->maskEmail($to),
                'recipientDomain' => $this->emailDomain($to),
                'subject' => $subject,
                'error' => $exception->getMessage(),
                'smtpHost' => $this->env('SMTP_HOST'),
                'smtpPort' => $this->env('SMTP_PORT', '465'),
                'smtpSecure' => strtolower($this->env('SMTP_SECURE', 'ssl')),
            ]);

            return [
                'ok' => false,
                'error' => 'EMAIL_SEND_FAILED',
                'message' => 'Unable to send email right now. Please try again later.',
            ];
        }
    }

    private function createMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $this->env('SMTP_HOST');
        $mailer->Port = (int) $this->env('SMTP_PORT', '465');
        $mailer->SMTPAuth = true;
        $mailer->Username = $this->env('SMTP_USER');
        $mailer->Password = $this->env('SMTP_PASS');
        $mailer->CharSet = 'UTF-8';
        $mailer->isHTML(true);

        $secure = strtolower($this->env('SMTP_SECURE', 'ssl'));
        if ($secure === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        return $mailer;
    }

    private function ccRecipients(): array
    {
        $raw = [
            $this->env('EMAIL_CC'),
            $this->env('EVENT_TICKET_CC_EMAIL'),
        ];

        $cc = [];
        foreach ($raw as $value) {
            foreach (preg_split('/[,;]+/', (string) $value) ?: [] as $item) {
                $email = trim($item);
                if ($email !== '') {
                    $cc[strtolower($email)] = $email;
                }
            }
        }

        return array_values($cc);
    }

    private function venueAddress(): string
    {
        return $this->env('EVENT_VENUE_ADDRESS', 'Namaste Kalyan');
    }

    private function supportPhone(): string
    {
        return $this->env('EVENT_BOOKING_PHONE', '9371519999');
    }

    private function assetUrl(string $relativePath): string
    {
        $base = rtrim(SiteUrl::resolve('home'), '/');
        return $base . '/' . ltrim($relativePath, '/');
    }

    private function absolutizeUrl(string $url): string
    {
        $value = trim($url);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $value) === 1) {
            return $value;
        }

        return rtrim(SiteUrl::resolve('home'), '/') . '/' . ltrim($value, '/');
    }

    private function formatDateTime(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '-';
        }

        try {
            $date = new \DateTimeImmutable($raw, new \DateTimeZone('Asia/Kolkata'));
            return $date->format('d M Y, h:i A');
        } catch (\Throwable) {
            return $raw;
        }
    }

    private function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return trim((string) $value);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function maskEmail(string $email): string
    {
        $value = trim($email);
        if ($value === '' || strpos($value, '@') === false) {
            return $value;
        }

        [$local, $domain] = explode('@', $value, 2);
        if ($local === '') {
            return '***@' . $domain;
        }

        $visiblePrefix = substr($local, 0, 2);
        return $visiblePrefix . '***@' . $domain;
    }

    private function emailDomain(string $email): string
    {
        $value = trim($email);
        if ($value === '' || strpos($value, '@') === false) {
            return '';
        }

        return strtolower((string) substr(strrchr($value, '@'), 1));
    }
}