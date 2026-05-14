<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantCategoryResource\Pages;
use App\Models\RestaurantCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RestaurantCategoryResource extends Resource
{
    protected static ?string $model = RestaurantCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Restaurants';
    protected static ?string $navigationLabel = 'Categories';
    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Categorie de restaurant')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nom')
                        ->placeholder('Ex: Fast Food, Africain, Pizza...')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->placeholder('Genere automatiquement')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icone (nom Ionicons)')
                        ->placeholder('Ex: fast-food-outline, flame-outline, pizza-outline...')
                        ->helperText('Nom d\'icone Ionicons. Exemples: fast-food-outline, flame-outline, pizza-outline, fish-outline, cafe-outline, beer-outline, ice-cream-outline, leaf-outline, restaurant-outline')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('color')
                        ->label('Couleur')
                        ->placeholder('Ex: #FF6B35')
                        ->maxLength(20),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordre d\'affichage')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Visible')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')
                    ->label('Icone')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('restaurants_count')
                    ->label('Restaurants')
                    ->counts('restaurants')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRestaurantCategories::route('/'),
            'create' => Pages\CreateRestaurantCategory::route('/create'),
            'edit'   => Pages\EditRestaurantCategory::route('/{record}/edit'),
        ];
    }
}
