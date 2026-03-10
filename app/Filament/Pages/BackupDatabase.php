<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDatabase extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';
    protected static string|\UnitEnum|null $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Backup Database';
    protected static ?int $navigationSort = 9;
    protected static ?string $title = 'Backup Database';

    protected string $view = 'filament.pages.backup-database';

    protected const BACKUP_DIR = 'backups';

    /**
     * Get the path to the current SQLite database file.
     */
    protected function getDatabasePath(): string
    {
        return config('database.connections.sqlite.database')
            ?? database_path('database.sqlite');
    }

    /**
     * Get info about the current database file.
     * @return array{size: int, modified: string, exists: bool}
     */
    public function getDatabaseInfo(): array
    {
        $path = $this->getDatabasePath();
        if (! file_exists($path)) {
            return ['size' => 0, 'modified' => '-', 'exists' => false];
        }

        return [
            'size'     => filesize($path),
            'modified' => date('Y-m-d H:i:s', filemtime($path)),
            'exists'   => true,
        ];
    }

    /**
     * List existing backup files sorted newest-first.
     * @return array<int, array{name: string, size: int, date: string}>
     */
    public function getBackups(): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::BACKUP_DIR)) {
            return [];
        }

        $files = $disk->files(self::BACKUP_DIR);
        $backups = [];

        foreach ($files as $file) {
            if (str_ends_with($file, '.sqlite')) {
                $backups[] = [
                    'name' => basename($file),
                    'size' => $disk->size($file),
                    'date' => date('Y-m-d H:i:s', $disk->lastModified($file)),
                ];
            }
        }

        // Sort newest first
        usort($backups, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $backups;
    }

    /**
     * Format bytes to a human-readable string.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        $factor = min($factor, count($units) - 1);

        return sprintf("%.{$precision}f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_backup')
                ->label('Create Backup')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Create a new database backup?')
                ->modalDescription('A snapshot of the current database will be saved. This may take a moment for large databases.')
                ->modalSubmitActionLabel('Yes, create backup')
                ->action(function (): void {
                    $this->createBackup();
                }),
            Action::make('delete_all_backups')
                ->label('Delete All Backups')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete ALL backups?')
                ->modalDescription('This will permanently delete every backup file. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete all')
                ->action(function (): void {
                    $this->deleteAllBackups();
                }),
        ];
    }

    /**
     * Create a timestamped backup of the SQLite database.
     */
    public function createBackup(): void
    {
        $source = $this->getDatabasePath();

        if (! file_exists($source)) {
            Notification::make()
                ->title('Backup failed')
                ->body('Database file not found.')
                ->danger()
                ->send();
            return;
        }

        $filename = 'database_' . date('Y-m-d_His') . '.sqlite';
        $disk = Storage::disk('local');

        if (! $disk->exists(self::BACKUP_DIR)) {
            $disk->makeDirectory(self::BACKUP_DIR);
        }

        $destination = $disk->path(self::BACKUP_DIR . '/' . $filename);

        if (copy($source, $destination)) {
            Notification::make()
                ->title('Backup created')
                ->body("Saved as {$filename}")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Backup failed')
                ->body('Could not copy the database file.')
                ->danger()
                ->send();
        }
    }

    /**
     * Download a specific backup file.
     */
    public function downloadBackup(string $filename): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        $path = self::BACKUP_DIR . '/' . $filename;

        abort_unless($disk->exists($path), 404, 'Backup not found.');

        $fullPath = $disk->path($path);

        return response()->download($fullPath, $filename);
    }

    /**
     * Delete a specific backup file (wired from the Blade view).
     */
    public function deleteBackup(string $filename): void
    {
        $disk = Storage::disk('local');
        $path = self::BACKUP_DIR . '/' . $filename;

        if ($disk->exists($path)) {
            $disk->delete($path);
            Notification::make()
                ->title('Backup deleted')
                ->body("{$filename} has been removed.")
                ->success()
                ->send();
        }
    }

    /**
     * Delete all backup files.
     */
    public function deleteAllBackups(): void
    {
        $disk = Storage::disk('local');
        $files = $disk->files(self::BACKUP_DIR);
        $count = 0;

        foreach ($files as $file) {
            if (str_ends_with($file, '.sqlite')) {
                $disk->delete($file);
                $count++;
            }
        }

        Notification::make()
            ->title('All backups deleted')
            ->body("{$count} backup file(s) removed.")
            ->success()
            ->send();
    }
}
