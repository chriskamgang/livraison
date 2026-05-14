<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuCategoryResource\Pages;
use App\Filament\Traits\HasRestaurantScope;
use App\Models\MenuCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuCategoryResource extends Resource
{
    use HasRestaurantScope;

    protected static ?string $model = MenuCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Menu';
    protected static ?string $navigationLabel = 'Categories';
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
            Forms\Components\Section::make('Categorie de produits')
                ->schema([
                    Forms\Components\Hidden::make('restaurant_id')
                        ->default(fn () => static::getRestaurantId() ?? \App\Models\Restaurant::first()?->id),
                    Forms\Components\Select::make('restaurant_id')
                        ->label('Restaurant')
                        ->relationship('restaurant', 'name')
                        ->required()
                        ->visible(fn () => static::isSuperAdmin()),
                    Forms\Components\TextInput::make('name')
                        ->label('Nom de la categorie')
                        ->placeholder('Ex: Burgers, Boissons, Desserts...')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordre d\'affichage')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Categorie visible')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->badge()
                    ->color('warning')
                    ->visible(fn () => static::isSuperAdmin()),
                Tables\Columns\TextColumn::make('name')
                    ->label('Categorie')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Nb articles')
                    ->counts('items')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Visible'),
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
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuCategories::route('/'),
            'create' => Pages\CreateMenuCategory::route('/create'),
            'edit'   => Pages\EditMenuCategory::route('/{record}/edit'),
        ];
    }
}
