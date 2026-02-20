<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Parses external bank transaction files in .xls/.xlsx format.
 *
 * Expected layout:
 * - Column B: Date
 * - Column C: Transaction Type
 * - Column D & E: Description (combined)
 * - Column F: Amount
 * - Column H: Balance
 * - Data starts at row 6 (1-based).
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
     * @return array<int, array{transaction_date: \Carbon\Carbon|null, transaction_type: string, description: string, amount: float, balance: string|float|null, row_index: int}>
     */
    public function parse(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $rows = [];

        for ($row = self::START_ROW; $row <= $highestRow; $row++) {
            $dateRaw = $this->getCellValue($sheet, self::COL_DATE, $row);
            $transactionDate = $this->parseDate($dateRaw);

            $transactionType = (string) $this->getCellValue($sheet, self::COL_TRANSACTION_TYPE, $row);
            $descD = (string) $this->getCellValue($sheet, self::COL_DESCRIPTION_D, $row);
            $descE = (string) $this->getCellValue($sheet, self::COL_DESCRIPTION_E, $row);
            $description = trim($descD . ' ' . $descE) ?: null;

            $amountRaw = $this->getCellValue($sheet, self::COL_AMOUNT, $row);
            $amount = $this->parseAmount($amountRaw);

            if ($amount === null && $transactionDate === null && ($description === null || $description === '')) {
                continue;
            }

            $balance = $this->getCellValue($sheet, self::COL_BALANCE, $row);

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
