<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryResource\Pages;
use App\Filament\Resources\DeliveryResource\RelationManagers;
use App\Models\Delivery;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeliveryResource extends Resource
{
    protected static ?string $model = Delivery::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Commandes';
    protected static ?string $navigationLabel = 'Livraisons';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('order_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('driver_id')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('restaurant_latitude')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('restaurant_longitude')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('delivery_latitude')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('delivery_longitude')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('delivery_address')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('delivery_address_details')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('distance_km')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('estimated_minutes')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('delivery_proof_photo')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\DateTimePicker::make('assigned_at'),
                Forms\Components\DateTimePicker::make('picked_up_at'),
                Forms\Components\DateTimePicker::make('delivered_at'),
                Forms\Components\TextInput::make('driver_earnings')
                    ->required()
                    ->numeric()
                    ->default(0.00),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('restaurant_latitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('restaurant_longitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivery_latitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivery_longitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivery_address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('delivery_address_details')
                    ->searchable(),
                Tables\Columns\TextColumn::make('distance_km')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_minutes')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivery_proof_photo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('assigned_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('picked_up_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivered_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver_earnings')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveries::route('/'),
            'create' => Pages\CreateDelivery::route('/create'),
            'edit' => Pages\EditDelivery::route('/{record}/edit'),
        ];
    }
}
