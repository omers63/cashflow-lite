<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Parses external bank transaction files in .xls/.xlsx format.
 *
 * Column layout can be overridden via Settings → Templates → "Import Excel/CSV column mapping" (JSON).
 * Default layout: B=Date, C=Type, D&E=Description, F=Amount, H=Balance; data starts at row 6.
 */
class ExternalBankExcelParser
{
    public const START_ROW = 6;

    public const COL_DATE = 2;        // B
    public const COL_TRANSACTION_TYPE = 3; // C
    public const COL_DESCRIPTION_D = 4;    // D
    public const COL_DESCRIPTION_E = 5;   // E
    public const COL_AMOUNT = 6;          // F
    public const COL_BALANCE = 8;         // H

    /**
     * Load column mapping from Settings (import_excel_mapping template) or use built-in defaults.
     *
     * @return array{start_row: int, col_date: int, col_transaction_type: int, col_description_d: int, col_description_e: int, col_amount: int, col_balance: int}
     */
    public static function getMapping(): array
    {
        $json = Setting::get('import_excel_mapping', '');
        if ($json !== null && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return [
                    'start_row' => (int) ($decoded['start_row'] ?? self::START_ROW),
                    'col_date' => (int) ($decoded['col_date'] ?? self::COL_DATE),
                    'col_transaction_type' => (int) ($decoded['col_transaction_type'] ?? self::COL_TRANSACTION_TYPE),
                    'col_description_d' => (int) ($decoded['col_description_d'] ?? self::COL_DESCRIPTION_D),
                    'col_description_e' => (int) ($decoded['col_description_e'] ?? self::COL_DESCRIPTION_E),
                    'col_amount' => (int) ($decoded['col_amount'] ?? self::COL_AMOUNT),
                    'col_balance' => (int) ($decoded['col_balance'] ?? self::COL_BALANCE),
                ];
            }
        }

        return [
            'start_row' => self::START_ROW,
            'col_date' => self::COL_DATE,
            'col_transaction_type' => self::COL_TRANSACTION_TYPE,
            'col_description_d' => self::COL_DESCRIPTION_D,
            'col_description_e' => self::COL_DESCRIPTION_E,
            'col_amount' => self::COL_AMOUNT,
            'col_balance' => self::COL_BALANCE,
        ];
    }

    /**
     * @return array<int, array{transaction_date: \Carbon\Carbon|null, transaction_type: string, description: string, amount: float, balance: string|float|null, row_index: int}>
     */
    public function parse(string $path): array
    {
        $map = self::getMapping();
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $rows = [];
        $startRow = $map['start_row'];

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $dateRaw = $this->getCellValue($sheet, $map['col_date'], $row);
            $transactionDate = $this->parseDate($dateRaw);

            $transactionType = (string) $this->getCellValue($sheet, $map['col_transaction_type'], $row);
            $descD = (string) $this->getCellValue($sheet, $map['col_description_d'], $row);
            $descE = (string) $this->getCellValue($sheet, $map['col_description_e'], $row);
            $description = trim($descD . ' ' . $descE) ?: null;

            $amountRaw = $this->getCellValue($sheet, $map['col_amount'], $row);
            $amount = $this->parseAmount($amountRaw);

            if ($amount === null && $transactionDate === null && ($description === null || $description === '')) {
                continue;
            }

            $balance = $this->getCellValue($sheet, $map['col_balance'], $row);

            $rows[] = [
                'transaction_date' => $transactionDate ?? now(),
                'transaction_type' => $transactionType,
                'description' => $description ?? '',
                'amount' => $amount ?? 0.0,
                'balance' => $balance,
                'row_index' => $row,
            ];
        }

        return $rows;
    }

    private function getCellValue($sheet, int $column, int $row)
    {
        $value = $sheet->getCellByColumnAndRow($column, $row)->getValue();
        return $value;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                $date = ExcelDate::excelToDateTimeObject($value);
                return Carbon::instance($date);
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return $cleaned !== '' ? (float) $cleaned : null;
    }
}
