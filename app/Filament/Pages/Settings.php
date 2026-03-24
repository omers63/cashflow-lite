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

        $loanTiersJson = Setting::get('loan_tiers');
        $loanTiers = $loanTiersJson ? json_decode($loanTiersJson, true) : config('settings.loan_tiers', []);

        $this->form->fill([
            'parameters' => $parameterState,
            'templates' => $templateState,
            'admin_widgets' => $adminWidgets,
            'member_widgets' => $memberWidgets,
            'loan_tiers' => $loanTiers,
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

                        Components\Tabs\Tab::make('Loan Tiers')
                            ->icon('heroicon-o-presentation-chart-line')
                            ->schema([
                                Forms\Components\Repeater::make('loan_tiers')
                                    ->label('Defined Loan Tiers')
                                    ->helperText('Define different tiers for loan eligibility based on member contributions.')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Tier Name')
                                            ->required(),
                                        Forms\Components\TextInput::make('min_amount')
                                            ->label('Min Amount ($)')
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\TextInput::make('max_amount')
                                            ->label('Max Amount ($)')
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\TextInput::make('installment_amount')
                                            ->label('Installment ($)')
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\TextInput::make('maturity_percentage')
                                            ->label('Target (%)')
                                            ->numeric()
                                            ->default(16)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->required(),
                                        Forms\Components\TextInput::make('allocation_percentage')
                                            ->label('Allocation (%)')
                                            ->numeric()
                                            ->default(10)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->required(),
                                        Forms\Components\TextInput::make('term_months')
                                            ->label('Term (months)')
                                            ->numeric()
                                            ->default(12)
                                            ->minValue(1)
                                            ->maxValue(360)
                                            ->required(),
                                        Forms\Components\TextInput::make('interest_rate')
                                            ->label('Interest rate (%)')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->required(),
                                    ])
                                    ->columns(8)
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                    ->collapsible()
                                    ->reorderable(false)
                                    ->defaultItems(1)
                                    ->columnSpanFull(),
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

        // Save loan tiers as JSON
        Setting::set('loan_tiers', json_encode($data['loan_tiers'] ?? []));

        Notification::make()
            ->title('Settings saved')
            ->body('Settings and loan tiers have been updated.')
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
