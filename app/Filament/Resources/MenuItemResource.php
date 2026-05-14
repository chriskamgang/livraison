<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Filament\Traits\HasRestaurantScope;
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
    use HasRestaurantScope;

    protected static ?string $model = MenuItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Menu';
    protected static ?string $navigationLabel = 'Articles';
    protected static ?int $navigationSort = 2;

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
        $restaurantId = static::getRestaurantId();

        return $form->schema([
            Forms\Components\Section::make('Informations du produit')
                ->schema([
                    Forms\Components\Hidden::make('restaurant_id')
                        ->default(fn () => $restaurantId),
                    Forms\Components\Select::make('restaurant_id')
                        ->label('Restaurant')
                        ->relationship('restaurant', 'name')
                        ->required()
                        ->live()
                        ->visible(fn () => static::isSuperAdmin()),
                    Forms\Components\Select::make('menu_category_id')
                        ->label('Categorie')
                        ->options(function (Forms\Get $get) use ($restaurantId) {
                            $rid = $restaurantId ?? $get('restaurant_id');
                            if (!$rid) return [];
                            return MenuCategory::where('restaurant_id', $rid)->orderBy('sort_order')->pluck('name', 'id');
                        })
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->label('Nom du produit')
                        ->placeholder('Ex: Cheese Burger, Coca-Cola...')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('price')
                        ->label('Prix (XAF)')
                        ->numeric()
                        ->required()
                        ->suffix('XAF'),
                    Forms\Components\TextInput::make('discount_price')
                        ->label('Prix promo (XAF)')
                        ->numeric()
                        ->suffix('XAF')
                        ->helperText('Laisser vide si pas de promotion'),
                    Forms\Components\TextInput::make('preparation_time')
                        ->label('Temps de preparation (min)')
                        ->numeric()
                        ->default(15)
                        ->suffix('min'),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->placeholder('Ingredients, allergenes...')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image')
                        ->label('Photo principale')
                        ->image()
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeTargetWidth('800')
                        ->imageResizeTargetHeight('800')
                        ->directory('menu-items'),
                    Forms\Components\FileUpload::make('images')
                        ->label('Galerie photos (max 5)')
                        ->image()
                        ->multiple()
                        ->maxFiles(5)
                        ->reorderable()
                        ->imageResizeTargetWidth('800')
                        ->imageResizeTargetHeight('800')
                        ->directory('menu-items/gallery'),
                ])->columns(2),

            Forms\Components\Section::make('Groupes d\'options / Complements')
                ->description('Ajoutez des choix pour le client (ex: type de frites, sauce, taille)')
                ->schema([
                    Forms\Components\Select::make('optionGroups')
                        ->label('Groupes d\'options')
                        ->multiple()
                        ->relationship('optionGroups', 'name', function (Builder $query) use ($restaurantId) {
                            if ($restaurantId) {
                                $query->where('restaurant_id', $restaurantId);
                            }
                        })
                        ->preload()
                        ->helperText('Selectionnez les groupes d\'options applicables a ce produit. Creez-les dans la section "Groupes d\'options".'),
                ]),

            Forms\Components\Section::make('Parametres')
                ->schema([
                    Forms\Components\Toggle::make('is_available')
                        ->label('Disponible')
                        ->default(true),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Mis en avant'),
                    Forms\Components\Toggle::make('is_vegetarian')
                        ->label('Vegetarien'),
                    Forms\Components\Toggle::make('is_spicy')
                        ->label('Epice'),
                    Forms\Components\TextInput::make('calories')
                        ->label('Calories (kcal)')
                        ->numeric()
                        ->suffix('kcal'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordre')
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
                    ->circular(),
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->badge()
                    ->color('warning')
                    ->visible(fn () => static::isSuperAdmin()),
                Tables\Columns\TextColumn::make('name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categorie')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prix')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ') . ' XAF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_price')
                    ->label('Promo')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 0, ',', ' ') . ' XAF' : '-')
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('Dispo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('menu_category_id')
                    ->label('Categorie')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_available')->label('Disponible'),
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

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit'   => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
