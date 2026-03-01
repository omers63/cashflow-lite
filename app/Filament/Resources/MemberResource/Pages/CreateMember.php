<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    public function mutateFormDataBeforeCreate(array $data): array
    {
        $user = User::create([
            'user_code' => User::generateUserCode(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        $data['user_id'] = $user->id;
        unset($data['name'], $data['email'], $data['password'], $data['phone'], $data['status']);

        return $data;
    }
}
