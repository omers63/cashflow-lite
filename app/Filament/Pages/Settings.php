<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class Settings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Settings';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Settings';
    protected static ?string $slug = 'settings';
    protected string $view = 'filament.pages.settings';

    public function mount(): void
    {
        $params = Setting::getByGroup(Setting::GROUP_PARAMETER);
        $templates = Setting::getByGroup(Setting::GROUP_TEMPLATE);
        $paramConfig = config('settings.parameters', []);
        $templateConfig = config('settings.templates', []);

        $parameterState = [];
        foreach ($paramConfig as $key => $def) {
            $parameterState[$key] = $params[$key] ?? $def['default'] ?? '';
        }
        $templateState = [];
        foreach ($templateConfig as $key => $def) {
            $templateState[$key] = $templates[$key] ?? $def['default'] ?? '';
        }

        $this->form->fill([
            'parameters' => $parameterState,
            'templates' => $templateState,
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Settings';
    }

    public function form(Schema $schema): Schema
    {
        $paramConfig = config('settings.parameters', []);
        $templateConfig = config('settings.templates', []);

        $parameterFields = [];
        foreach ($paramConfig as $key => $def) {
            $type = $def['type'] ?? 'string';
            $field = $type === 'integer'
                ? Forms\Components\TextInput::make("parameters.{$key}")
                    ->numeric()
                    ->integer()
                : Forms\Components\TextInput::make("parameters.{$key}");
            $parameterFields[] = $field
                ->label($def['label'] ?? $key)
                ->helperText($def['help'] ?? null)
                ->default($def['default'] ?? '')
                ->maxLength(255);
        }

        $templateFields = [];
        foreach ($templateConfig as $key => $def) {
            $templateFields[] = Forms\Components\Textarea::make("templates.{$key}")
                ->label($def['label'] ?? $key)
                ->helperText($def['help'] ?? null)
                ->default($def['default'] ?? '')
                ->rows(6)
                ->columnSpanFull();
        }

        return $schema
            ->schema([
                Components\Tabs::make('Settings')
                    ->tabs([
                        Components\Tabs\Tab::make('Key parameters')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Components\Grid::make(2)
                                    ->schema($parameterFields),
                            ]),

                        Components\Tabs\Tab::make('Templates')
                            ->icon('heroicon-o-document-text')
                            ->schema($templateFields),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        Setting::setByGroup(Setting::GROUP_PARAMETER, $data['parameters'] ?? []);
        Setting::setByGroup(Setting::GROUP_TEMPLATE, $data['templates'] ?? []);
        Notification::make()
            ->title('Settings saved')
            ->body('Parameters and templates have been updated.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),
        ];
    }
}
