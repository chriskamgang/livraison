<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantResource\Pages;
use App\Filament\Traits\HasRestaurantScope;
use App\Models\Restaurant;
use App\Models\RestaurantCategory;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RestaurantResource extends Resource
{
    use HasRestaurantScope;

    protected static ?string $model = Restaurant::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Restaurants';
    protected static ?string $navigationLabel = 'Restaurants';
    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return static::isSuperAdmin() ? 'Restaurants' : 'Mon Restaurant';
    }

    public static function getNavigationGroup(): ?string
    {
        return static::isSuperAdmin() ? 'Restaurants' : 'Parametres';
    }

    // Restaurant admin ne peut pas créer de restaurant
    public static function canCreate(): bool
    {
        return static::isSuperAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $restaurantId = static::getRestaurantId();

        if ($restaurantId) {
            $query->where('id', $restaurantId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations generales')
                ->schema([
                    Forms\Components\Select::make('category_id')
                        ->label('Categorie')
                        ->options(RestaurantCategory::pluck('name', 'id'))
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telephone')
                        ->tel()
                        ->required(),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email(),
                    Forms\Components\FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->directory('restaurants/logos')
                        ->imageResizeTargetWidth('200')
                        ->imageResizeTargetHeight('200'),
                    Forms\Components\FileUpload::make('cover_image')
                        ->label('Photo de couverture')
                        ->image()
                        ->directory('restaurants/covers')
                        ->imageResizeTargetWidth('1200')
                        ->imageResizeTargetHeight('600'),
                ])->columns(2),

            Forms\Components\Section::make('Localisation')
                ->schema([
                    Forms\Components\TextInput::make('address')
                        ->label('Adresse')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('city')
                        ->label('Ville')
                        ->default('Bafoussam'),
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
                        ->default(true)
                        ->visible(fn () => static::isSuperAdmin()),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('En vedette')
                        ->visible(fn () => static::isSuperAdmin()),
                    Forms\Components\Toggle::make('is_open')
                        ->label('Ouvert maintenant')
                        ->default(true),
                ])->columns(3),

            // Section admin restaurant — visible seulement pour super admin
            Forms\Components\Section::make('Administrateur du restaurant')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Proprietaire')
                        ->options(User::where('role', 'restaurant_admin')->pluck('name', 'id'))
                        ->searchable()
                        ->helperText('Selectionnez le proprietaire ou creez-en un depuis Utilisateurs > Admins Restaurant'),
                ])
                ->visible(fn () => static::isSuperAdmin()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categorie')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('city')
                    ->label('Ville')
                    ->searchable(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Proprietaire')
                    ->visible(fn () => static::isSuperAdmin())
                    ->default('Non assigne'),
                Tables\Columns\TextColumn::make('delivery_fee')
                    ->label('Livraison')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ') . ' XAF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Note')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . ' / 5')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_open')
                    ->label('Ouvert')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Vedette')
                    ->boolean()
                    ->visible(fn () => static::isSuperAdmin()),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Actif'),
                Tables\Filters\TernaryFilter::make('is_open')->label('Ouvert'),
            ])
            ->actions([
                Tables\Actions\Action::make('impersonate')
                    ->label('Connexion')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('success')
                    ->visible(fn () => static::isSuperAdmin())
                    ->url(fn (Restaurant $record) => route('admin.restaurants.impersonate', $record)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => static::isSuperAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => static::isSuperAdmin()),
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
