<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Repositories\LeadRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CrmLeadExportService
{
    private LeadRepository $leads;

    public function __construct()
    {
        $this->leads = new LeadRepository();
    }

    public function export(array $filters): string
    {
        $rows = $this->leads->listForExport($filters);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CRM Leads');

        $data = [[
            'Created At',
            'Mobile',
            'Name',
            'Prize',
            'Outcome',
            'Coupon Code',
            'Lead Status',
            'Redeemed At',
            'Source',
            'Date Of Birth',
            'Date Of Anniversary',
            'Visit Count',
            'CRM Sync Status',
            'CRM Sync Code',
            'CRM Sync Message',
        ]];

        foreach ($rows as $row) {
            $prize = trim((string) ($row['prize'] ?? ''));
            $outcome = stripos($prize, 'Try Again') !== false
                ? 'Try Again'
                : ($prize !== '' ? 'Won' : 'Pending');

            $data[] = [
                (string) ($row['created_at'] ?? ''),
                (string) ($row['phone'] ?? ''),
                (string) ($row['name'] ?? ''),
                $prize,
                $outcome,
                (string) ($row['coupon_code'] ?? ''),
                (string) ($row['status'] ?? ''),
                (string) ($row['redeemed_at'] ?? ''),
                (string) ($row['source'] ?? ''),
                (string) ($row['date_of_birth'] ?? ''),
                (string) ($row['date_of_anniversary'] ?? ''),
                (int) ($row['visit_count'] ?? 0),
                (string) ($row['crm_sync_status'] ?? ''),
                (string) ($row['crm_sync_code'] ?? ''),
                (string) ($row['crm_sync_message'] ?? ''),
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

        $filePath = $tmpDir . DIRECTORY_SEPARATOR . 'crm_leads_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }
}