<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class EventTransactionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO event_transactions (
                    transaction_id, event_id, event_title,
                    customer_name, customer_email, customer_phone,
                    qty, amount, currency, gateway,
                    order_id, payment_id, status,
                    qr_url, qr_payload,
                    created_at, paid_at,
                    attendee_details, guest_passes_json
                ) VALUES (
                    :transaction_id, :event_id, :event_title,
                    :customer_name, :customer_email, :customer_phone,
                    :qty, :amount, :currency, :gateway,
                    :order_id, :payment_id, :status,
                    :qr_url, :qr_payload,
                    :created_at, :paid_at,
                    :attendee_details, :guest_passes_json
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':transaction_id'    => $payload['transaction_id'],
            ':event_id'          => $payload['event_id'],
            ':event_title'       => $payload['event_title'] ?? '',
            ':customer_name'     => $payload['customer_name'] ?? '',
            ':customer_email'    => $payload['customer_email'] ?? '',
            ':customer_phone'    => $payload['customer_phone'] ?? '',
            ':qty'               => (int) ($payload['qty'] ?? 1),
            ':amount'            => (float) ($payload['amount'] ?? 0),
            ':currency'          => $payload['currency'] ?? 'INR',
            ':gateway'           => $payload['gateway'] ?? 'free',
            ':order_id'          => $payload['order_id'] ?? null,
            ':payment_id'        => $payload['payment_id'] ?? null,
            ':status'            => $payload['status'] ?? 'pending',
            ':qr_url'            => $payload['qr_url'] ?? '',
            ':qr_payload'        => $payload['qr_payload'] ?? '',
            ':created_at'        => $payload['created_at'] ?? date('Y-m-d H:i:s'),
            ':paid_at'           => $payload['paid_at'] ?? null,
            ':attendee_details'  => json_encode($payload['attendee_details'] ?? [], JSON_UNESCAPED_UNICODE),
            ':guest_passes_json' => json_encode($payload['guest_passes_json'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByOrderId(string $orderId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM event_transactions WHERE order_id = :order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByTransactionId(string $transactionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM event_transactions WHERE transaction_id = :transaction_id LIMIT 1');
        $stmt->execute([':transaction_id' => $transactionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findLatestForEventAndCustomer(string $eventId, string $email, string $phone): ?array
    {
        $sql = 'SELECT *
                FROM event_transactions
                WHERE event_id = :event_id
                  AND (
                    (:email <> "" AND LOWER(customer_email) = LOWER(:email))
                    OR
                    (:phone <> "" AND customer_phone = :phone)
                  )
                  AND status IN ("pending", "paid", "free_confirmed")
                ORDER BY id DESC
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':event_id' => $eventId,
            ':email'    => trim($email),
            ':phone'    => trim($phone),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markPaid(string $transactionId, string $paymentId, string $status = 'paid'): void
    {
        $sql = 'UPDATE event_transactions
                SET payment_id = :payment_id,
                    status = :status,
                    paid_at = :paid_at
                WHERE transaction_id = :transaction_id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':payment_id'     => $paymentId,
            ':status'         => $status,
            ':paid_at'        => date('Y-m-d H:i:s'),
            ':transaction_id' => $transactionId,
        ]);
    }

    public function setOrderId(string $transactionId, string $orderId): void
    {
        $stmt = $this->db->prepare('UPDATE event_transactions SET order_id = :order_id WHERE transaction_id = :transaction_id');
        $stmt->execute([
            ':order_id'       => $orderId,
            ':transaction_id' => $transactionId,
        ]);
    }

    public function setStatus(string $transactionId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE event_transactions SET status = :status WHERE transaction_id = :transaction_id');
        $stmt->execute([
            ':status'         => $status,
            ':transaction_id' => $transactionId,
        ]);
    }

    public function setEmailSent(string $transactionId, bool $sent): void
    {
        $stmt = $this->db->prepare('UPDATE event_transactions SET email_status = :status, email_sent_at = :sent_at WHERE transaction_id = :transaction_id');
        $stmt->execute([
            ':status'         => $sent ? 'Sent' : 'Failed',
            ':sent_at'        => $sent ? date('Y-m-d H:i:s') : null,
            ':transaction_id' => $transactionId,
        ]);
    }
}
