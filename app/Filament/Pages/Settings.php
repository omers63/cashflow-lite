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

    public ?array $data = [];

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

        // Dashboard widget toggles
        $widgetConfig = config('settings.dashboard_widgets', []);
        $adminJson = Setting::get('dashboard_widgets_admin');
        $memberJson = Setting::get('dashboard_widgets_member');
        $adminSaved = $adminJson ? json_decode($adminJson, true) : [];
        $memberSaved = $memberJson ? json_decode($memberJson, true) : [];

        $adminWidgets = [];
        foreach ($widgetConfig['admin'] ?? [] as $key => $def) {
            $adminWidgets[$key] = $adminSaved[$key] ?? $def['default'] ?? true;
        }
        $memberWidgets = [];
        foreach ($widgetConfig['member'] ?? [] as $key => $def) {
            $memberWidgets[$key] = $memberSaved[$key] ?? $def['default'] ?? true;
        }

        $this->form->fill([
            'parameters' => $parameterState,
            'templates' => $templateState,
            'admin_widgets' => $adminWidgets,
            'member_widgets' => $memberWidgets,
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
        $widgetConfig = config('settings.dashboard_widgets', []);

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

        // Widget toggle fields
        $adminWidgetFields = [];
        foreach ($widgetConfig['admin'] ?? [] as $key => $def) {
            $adminWidgetFields[] = Forms\Components\Toggle::make("admin_widgets.{$key}")
                ->label($def['label'] ?? $key)
                ->default($def['default'] ?? true);
        }
        $memberWidgetFields = [];
        foreach ($widgetConfig['member'] ?? [] as $key => $def) {
            $memberWidgetFields[] = Forms\Components\Toggle::make("member_widgets.{$key}")
                ->label($def['label'] ?? $key)
                ->default($def['default'] ?? true);
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

                        Components\Tabs\Tab::make('Dashboard Widgets')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Components\Section::make('Admin Dashboard')
                                    ->description('Choose which widgets appear on the admin dashboard.')
                                    ->schema([
                                        Components\Grid::make(3)
                                            ->schema($adminWidgetFields),
                                    ]),
                                Components\Section::make('Member Dashboard')
                                    ->description('Choose which sections appear on the member dashboard.')
                                    ->schema([
                                        Components\Grid::make(3)
                                            ->schema($memberWidgetFields),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        Setting::setByGroup(Setting::GROUP_PARAMETER, $data['parameters'] ?? []);
        Setting::setByGroup(Setting::GROUP_TEMPLATE, $data['templates'] ?? []);

        // Save widget toggles as JSON
        Setting::set('dashboard_widgets_admin', json_encode($data['admin_widgets'] ?? []));
        Setting::set('dashboard_widgets_member', json_encode($data['member_widgets'] ?? []));

        Notification::make()
            ->title('Settings saved')
            ->body('Parameters, templates, and dashboard widget preferences have been updated.')
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
