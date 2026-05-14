<?php

namespace App\Filament\Resources\RestaurantCategoryResource\Pages;

use App\Filament\Resources\RestaurantCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRestaurantCategory extends EditRecord
{
    protected static string $resource = RestaurantCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
