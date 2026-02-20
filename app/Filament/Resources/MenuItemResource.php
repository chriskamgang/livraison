<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Menu';
    protected static ?string $navigationLabel = 'Articles';
    protected static ?int $navigationSort = 2;

    // Filtre automatique sur le restaurant principal
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('restaurant_id', \App\Models\Restaurant::value('id'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations du produit')
                ->schema([
                    Forms\Components\Hidden::make('restaurant_id')
                        ->default(fn () => \App\Models\Restaurant::value('id')),
                    Forms\Components\Select::make('menu_category_id')
                        ->label('Catégorie')
                        ->relationship('category', 'name', fn ($query) => $query->orderBy('sort_order'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('name')
                        ->label('Nom du produit')
                        ->placeholder('Ex: Cheese Burger, Coca-Cola...')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('price')
                        ->label('Prix (XAF)')
                        ->numeric()
                        ->required()
                        ->suffix('XAF')
                        ->minValue(0),
                    Forms\Components\TextInput::make('discount_price')
                        ->label('Prix promo (XAF)')
                        ->numeric()
                        ->suffix('XAF')
                        ->helperText('Laisser vide si pas de promotion'),
                    Forms\Components\TextInput::make('preparation_time')
                        ->label('Temps de préparation (min)')
                        ->numeric()
                        ->default(15)
                        ->suffix('min'),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->placeholder('Ingrédients, allergènes...')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image')
                        ->label('Photo du produit')
                        ->image()
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeTargetWidth('800')
                        ->imageResizeTargetHeight('800')
                        ->directory('menu-items')
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Options & Caractéristiques')
                ->schema([
                    Forms\Components\Toggle::make('is_available')
                        ->label('Disponible')
                        ->default(true)
                        ->helperText('Désactiver si rupture de stock'),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Mis en avant')
                        ->helperText('Affiché en haut du menu'),
                    Forms\Components\Toggle::make('is_vegetarian')
                        ->label('Végétarien'),
                    Forms\Components\Toggle::make('is_spicy')
                        ->label('Épicé'),
                    Forms\Components\TextInput::make('calories')
                        ->label('Calories (kcal)')
                        ->numeric()
                        ->suffix('kcal'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordre d\'affichage')
                        ->numeric()
                        ->default(0),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl(asset('images/placeholder-food.png')),
                Tables\Columns\TextColumn::make('name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prix')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ') . ' XAF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_price')
                    ->label('Prix promo')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 0, ',', ' ') . ' XAF' : '—')
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('Dispo')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Vedette')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('menu_category_id')
                    ->label('Catégorie')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('Disponible'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('En vedette'),
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
            ->defaultSort('menu_category_id');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit'   => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
