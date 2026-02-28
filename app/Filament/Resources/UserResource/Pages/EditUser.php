<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    #[On('refreshUserRecord')]
    public function refreshUserRecord(?int $userId = null): void
    {
        if ($userId !== null && $this->record->getKey() !== $userId) {
            return;
        }
        $fresh = $this->record->fresh();
        if ($fresh) {
            $this->record = $fresh;
        } else {
            $this->record->refresh();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_user_bank_transactions')
                ->label('Import Bank Transactions')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->schema([
                    Forms\Components\FileUpload::make('file')
                        ->label('Transactions File (2 columns: Date, Amount)')
                        ->required()
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->storeFiles(false),
                ])
                ->action(function (array $data): void {
                    $user = $this->getRecord();
                    $file = $data['file'] ?? null;

                    if (!$user || !$file) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('User or file not found.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $path = $file->getRealPath();
                    if (!$path || !is_readable($path)) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('Unable to read uploaded file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $imported = 0;
                    $skipped = 0;

                    $extension = strtolower($file->getClientOriginalExtension() ?? '');

                    if (in_array($extension, ['xlsx', 'xls'])) {
                        // Parse Excel file: 2 columns (A: date, B: amount), data starts at row 2
                        try {
                            $spreadsheet = IOFactory::load($path);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Import failed')
                                ->body('Failed to read Excel file: ' . $e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $sheet = $spreadsheet->getActiveSheet();
                        $highestRow = $sheet->getHighestRow();

                        for ($row = 2; $row <= $highestRow; $row++) {
                            $dateRaw = $sheet->getCell("A{$row}")->getValue();
                            $amountRaw = $sheet->getCell("B{$row}")->getValue();

                            if (trim((string) $dateRaw) === '' || trim((string) $amountRaw) === '') {
                                $skipped++;
                                continue;
                            }

                            // Handle Excel serial dates and string dates
                            $date = null;
                            if (is_numeric($dateRaw)) {
                                try {
                                    $date = Carbon::instance(ExcelDate::excelToDateTimeObject($dateRaw));
                                } catch (\Throwable) {
                                    $date = null;
                                }
                            } else {
                                try {
                                    $date = Carbon::parse($dateRaw);
                                } catch (\Throwable) {
                                    $date = null;
                                }
                            }

                            if (! $date) {
                                $skipped++;
                                continue;
                            }

                            $amount = is_numeric($amountRaw)
                                ? (float) $amountRaw
                                : (float) str_replace([',', ' '], '', (string) $amountRaw);

                            if ($amount === 0.0) {
                                $skipped++;
                                continue;
                            }

                            try {
                                Transaction::create([
                                    'transaction_id' => Transaction::generateTransactionId('USR'),
                                    'transaction_date' => $date,
                                    'type' => 'adjustment',
                                    'from_account' => 'Imported User Bank',
                                    'to_account' => 'User Bank Account',
                                    'amount' => $amount,
                                    'user_id' => $user->id,
                                    'reference' => 'USER-IMPORT-' . $user->id,
                                    'status' => 'complete',
                                    'notes' => 'Imported from user bank transactions file',
                                    'created_by' => auth()->id(),
                                ]);

                                $imported++;
                            } catch (\Throwable $e) {
                                // Skip rows that violate unique constraints or other DB errors.
                                $skipped++;
                                continue;
                            }
                        }
                    } else {
                        // Fallback: parse as CSV / plain text
                        $handle = fopen($path, 'r');
                        if (!$handle) {
                            Notification::make()
                                ->title('Import failed')
                                ->body('Unable to open uploaded file.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $row = 0;

                        while (($columns = fgetcsv($handle, 0, ',')) !== false) {
                            $row++;

                            // Skip header row (row 1)
                            if ($row === 1) {
                                continue;
                            }

                            if (count($columns) < 2) {
                                $skipped++;
                                continue;
                            }

                            [$dateRaw, $amountRaw] = [$columns[0], $columns[1]];

                            if (trim((string) $dateRaw) === '' || trim((string) $amountRaw) === '') {
                                $skipped++;
                                continue;
                            }

                            try {
                                $date = Carbon::parse($dateRaw);
                            } catch (\Throwable $e) {
                                $skipped++;
                                continue;
                            }

                            $amount = (float) str_replace([',', ' '], '', (string) $amountRaw);

                            if ($amount === 0.0) {
                                $skipped++;
                                continue;
                            }

                            try {
                                Transaction::create([
                                    'transaction_id' => Transaction::generateTransactionId('USR'),
                                    'transaction_date' => $date,
                                    'type' => 'adjustment',
                                    'from_account' => 'Imported User Bank',
                                    'to_account' => 'User Bank Account',
                                    'amount' => $amount,
                                    'user_id' => $user->id,
                                    'reference' => 'USER-IMPORT-' . $user->id,
                                    'status' => 'complete',
                                    'notes' => 'Imported from user bank transactions file',
                                    'created_by' => auth()->id(),
                                ]);

                                $imported++;
                            } catch (\Throwable $e) {
                                // Skip rows that violate unique constraints or other DB errors.
                                $skipped++;
                                continue;
                            }
                        }

                        fclose($handle);
                    }

                    Notification::make()
                        ->title('Import complete')
                        ->body("{$imported} transaction(s) imported" . ($skipped > 0 ? ", {$skipped} skipped" : ''))
                        ->success()
                        ->send();
                }),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
