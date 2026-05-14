<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Filament\Traits\HasRestaurantScope;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CouponResource extends Resource
{
    use HasRestaurantScope;

    protected static ?string $model = Coupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Parametres';
    protected static ?string $navigationLabel = 'Coupons';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $restaurantId = static::getRestaurantId();

        if ($restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Coupon')
                ->schema([
                    Forms\Components\Hidden::make('restaurant_id')
                        ->default(fn () => static::getRestaurantId()),
                    Forms\Components\Select::make('restaurant_id')
                        ->label('Restaurant')
                        ->relationship('restaurant', 'name')
                        ->visible(fn () => static::isSuperAdmin()),
                    Forms\Components\TextInput::make('code')
                        ->label('Code')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('type')
                        ->label('Type')
                        ->options([
                            'percentage'    => 'Pourcentage',
                            'fixed_amount'  => 'Montant fixe',
                            'free_delivery' => 'Livraison gratuite',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('value')
                        ->label('Valeur')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('minimum_order')
                        ->label('Commande minimum (XAF)')
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('maximum_discount')
                        ->label('Remise maximum (XAF)')
                        ->numeric(),
                ])->columns(2),

            Forms\Components\Section::make('Limites')
                ->schema([
                    Forms\Components\TextInput::make('usage_limit')
                        ->label('Limite d\'utilisation')
                        ->numeric(),
                    Forms\Components\TextInput::make('per_user_limit')
                        ->label('Par utilisateur')
                        ->numeric()
                        ->default(1),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Actif')
                        ->default(true),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Debut'),
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expiration'),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->visible(fn () => static::isSuperAdmin()),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valeur')
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Utilisations')
                    ->formatStateUsing(fn ($record) => $record->usage_count . ($record->usage_limit ? " / {$record->usage_limit}" : '')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expiration')
                    ->dateTime('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit'   => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
