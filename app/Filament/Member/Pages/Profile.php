<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Profile extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Profile';
    protected static string|\UnitEnum|null $navigationGroup = 'Profile & Settings';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.member.pages.profile';

    public function getTitle(): string|Htmlable
    {
        return 'Profile & Settings';
    }

    public function getMember(): ?Member
    {
        return auth()->user()?->member;
    }
}

