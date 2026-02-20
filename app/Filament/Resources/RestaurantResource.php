<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantResource\Pages;
use App\Models\Restaurant;
use App\Models\RestaurantCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Paramètres';
    protected static ?string $navigationLabel = 'Mon Restaurant';
    protected static ?int $navigationSort = 2;

    // Un seul restaurant — on cache la création multiple
    public static function canCreate(): bool { return !\App\Models\Restaurant::exists(); }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations générales')
                ->schema([
                    Forms\Components\Select::make('category_id')
                        ->label('Catégorie')
                        ->options(RestaurantCategory::pluck('name', 'id'))
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('phone')
                        ->label('Téléphone')
                        ->tel()
                        ->required(),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email(),
                ])->columns(2),

            Forms\Components\Section::make('Localisation')
                ->schema([
                    Forms\Components\TextInput::make('address')
                        ->label('Adresse')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('city')
                        ->label('Ville')
                        ->default('Douala'),
                    Forms\Components\TextInput::make('latitude')
                        ->label('Latitude')
                        ->numeric(),
                    Forms\Components\TextInput::make('longitude')
                        ->label('Longitude')
                        ->numeric(),
                ])->columns(3),

            Forms\Components\Section::make('Livraison & Tarifs')
                ->schema([
                    Forms\Components\TextInput::make('delivery_fee')
                        ->label('Frais de livraison (XAF)')
                        ->numeric()
                        ->default(500),
                    Forms\Components\TextInput::make('minimum_order')
                        ->label('Commande minimum (XAF)')
                        ->numeric()
                        ->default(1000),
                    Forms\Components\TextInput::make('delivery_time_min')
                        ->label('Temps min (min)')
                        ->numeric()
                        ->default(20),
                    Forms\Components\TextInput::make('delivery_time_max')
                        ->label('Temps max (min)')
                        ->numeric()
                        ->default(40),
                ])->columns(4),

            Forms\Components\Section::make('Statut')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Actif')
                        ->default(true),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('En vedette'),
                    Forms\Components\Toggle::make('is_open')
                        ->label('Ouvert maintenant')
                        ->default(true),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('Photo')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('city')
                    ->label('Ville')
                    ->searchable(),
                Tables\Columns\TextColumn::make('delivery_fee')
                    ->label('Livraison')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ') . ' XAF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Note')
                    ->formatStateUsing(fn ($state) => '⭐ ' . number_format($state, 1))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_open')
                    ->label('Ouvert')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Vedette')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Actif'),
                Tables\Filters\TernaryFilter::make('is_open')->label('Ouvert'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('En vedette'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRestaurants::route('/'),
            'create' => Pages\CreateRestaurant::route('/create'),
            'edit'   => Pages\EditRestaurant::route('/{record}/edit'),
        ];
    }
}
