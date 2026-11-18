<?php

namespace App\Filament\Resources\RestaurantResource\Pages;

use App\Filament\Resources\RestaurantResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateRestaurant extends CreateRecord
{
    protected static string $resource = RestaurantResource::class;

    protected function afterCreate(): void
    {
        $data = $this->data;

        // Si on a rempli les champs de création d'un nouveau compte admin
        if (!empty($data['admin_email']) && !empty($data['admin_password'])) {
            $user = User::create([
                'name'          => $data['admin_name'] ?? $this->record->name,
                'email'         => $data['admin_email'],
                'password'      => Hash::make($data['admin_password']),
                'role'          => 'restaurant_admin',
                'restaurant_id' => $this->record->id,
                'is_active'     => true,
            ]);

            $this->record->update(['user_id' => $user->id]);
        }
    }
}
