<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Repositories\ContactRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CrmContactExportService
{
    private ContactRepository $contacts;

    public function __construct()
    {
        $this->contacts = new ContactRepository();
    }

    public function export(array $filters): string
    {
        $rows = $this->contacts->listForExport($filters);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CRM Contacts');

        $data = [
            [
                'Phone',
                'Name',
                'Date Of Birth',
                'Date Of Anniversary',
                'First Seen',
                'Last Seen',
                'Latest Source',
                'Total Submissions',
                'Latest Lead ID',
                'Latest Lead Created At',
                'Latest CRM Sync Status',
                'Latest CRM Sync Code',
                'Latest CRM Sync Message',
                'Last CRM Attempted At',
                'Last CRM Pushed At',
            ],
        ];

        foreach ($rows as $row) {
            $data[] = [
                (string) ($row['phone'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['date_of_birth'] ?? ''),
                (string) ($row['date_of_anniversary'] ?? ''),
                (string) ($row['first_seen_at'] ?? ''),
                (string) ($row['last_seen_at'] ?? ''),
                (string) ($row['latest_source'] ?? ''),
                (int) ($row['total_submissions'] ?? 0),
                (string) ($row['latest_lead_id'] ?? ''),
                (string) ($row['latest_lead_created_at'] ?? ''),
                (string) ($row['latest_crm_sync_status'] ?? ''),
                (string) ($row['latest_crm_sync_code'] ?? ''),
                (string) ($row['latest_crm_sync_message'] ?? ''),
                (string) ($row['last_crm_attempted_at'] ?? ''),
                (string) ($row['last_crm_pushed_at'] ?? ''),
            ];
        }

        $sheet->fromArray($data, null, 'A1');
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $tmpDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $filePath = $tmpDir . DIRECTORY_SEPARATOR . 'crm_contacts_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }
}