<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Restaurant;
use App\Models\RestaurantCategory;
use App\Models\MenuCategory;
use App\Models\MenuItem;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        // Categories de restaurants
        $catRestaurant = RestaurantCategory::firstOrCreate(['slug' => 'restaurant'], ['name' => 'Restaurant', 'icon' => 'restaurant', 'sort_order' => 0]);
        $catFastFood   = RestaurantCategory::firstOrCreate(['slug' => 'fast-food'], ['name' => 'Fast Food', 'icon' => 'fast-food', 'sort_order' => 1]);
        $catBraise     = RestaurantCategory::firstOrCreate(['slug' => 'braiserie'], ['name' => 'Braiserie', 'icon' => 'local-fire-department', 'sort_order' => 2]);
        $catJus        = RestaurantCategory::firstOrCreate(['slug' => 'jus-smoothies'], ['name' => 'Jus & Smoothies', 'icon' => 'local-cafe', 'sort_order' => 3]);
        $catPizza      = RestaurantCategory::firstOrCreate(['slug' => 'pizzeria'], ['name' => 'Pizzeria', 'icon' => 'local-pizza', 'sort_order' => 4]);

        // ============================================================
        // RESTAURANT 1 — Le Bon Gout
        // ============================================================
        $r1 = Restaurant::firstOrCreate(['slug' => 'le-bon-gout'], [
            'category_id' => $catRestaurant->id,
            'name' => 'Le Bon Goût',
            'description' => 'Cuisine camerounaise authentique, plats mijotés avec amour.',
            'logo' => 'restaurants/logos/le-bon-gout.jpg',
            'cover_image' => 'restaurants/covers/le-bon-gout.jpg',
            'phone' => '+237 699 100 001',
            'email' => 'contact@lebongout.cm',
            'address' => 'Rue de la Joie, Bafoussam',
            'latitude' => 5.4737,
            'longitude' => 10.4176,
            'city' => 'Bafoussam',
            'delivery_fee' => 500,
            'delivery_time_min' => 20,
            'delivery_time_max' => 40,
            'minimum_order' => 2000,
            'rating' => 4.5,
            'ratings_count' => 120,
            'is_active' => true,
            'is_featured' => true,
            'is_open' => true,
            'opening_hours' => [
                'mon' => ['open' => '10:00', 'close' => '22:00'],
                'tue' => ['open' => '10:00', 'close' => '22:00'],
                'wed' => ['open' => '10:00', 'close' => '22:00'],
                'thu' => ['open' => '10:00', 'close' => '22:00'],
                'fri' => ['open' => '10:00', 'close' => '23:00'],
                'sat' => ['open' => '10:00', 'close' => '23:00'],
                'sun' => ['open' => '12:00', 'close' => '21:00'],
            ],
        ]);
        $this->seedMenu($r1, [
            ['category' => 'Plats Principaux', 'items' => [
                ['name' => 'Ndolé Complet', 'price' => 3500, 'description' => 'Ndolé aux crevettes et viande, plantain mûr', 'image' => 'menu-items/ndole-complet.jpg'],
                ['name' => 'Eru & Waterfoufou', 'price' => 3000, 'description' => 'Eru frais avec waterfoufou et viande fumée', 'image' => 'menu-items/eru-combo.jpg'],
                ['name' => 'Taro Sauce Jaune', 'price' => 2800, 'description' => 'Taro pilé, sauce jaune aux légumes et poisson', 'image' => 'menu-items/taro-sauce.jpg'],
                ['name' => 'Poulet Braisé', 'price' => 4000, 'description' => 'Poulet entier braisé, épices maison', 'image' => 'menu-items/poulet-braise.jpg'],
            ]],
            ['category' => 'Accompagnements', 'items' => [
                ['name' => 'Plantain Grillé', 'price' => 500, 'description' => 'Plantain mûr grillé au charbon', 'image' => 'menu-items/plantain-grille.jpg'],
                ['name' => 'Riz Sauté', 'price' => 800, 'description' => 'Riz sauté aux légumes', 'image' => 'menu-items/riz-sautee.jpg'],
            ]],
            ['category' => 'Boissons', 'items' => [
                ['name' => 'Jus de Gingembre', 'price' => 500, 'description' => 'Gingembre frais pressé, citron, miel', 'image' => 'menu-items/jus-gingembre.jpg'],
                ['name' => 'Bière Locale 65cl', 'price' => 800, 'description' => 'Bière camerounaise bien fraîche', 'image' => 'menu-items/biere-locale.jpg'],
            ]],
        ]);

        // ============================================================
        // RESTAURANT 2 — Chez Mama Africa
        // ============================================================
        $r2 = Restaurant::firstOrCreate(['slug' => 'chez-mama-africa'], [
            'category_id' => $catRestaurant->id,
            'name' => 'Chez Mama Africa',
            'description' => 'Les saveurs de l\'Afrique dans votre assiette. Spécialités de l\'Ouest.',
            'logo' => 'restaurants/logos/chez-mama-africa.jpg',
            'cover_image' => 'restaurants/covers/chez-mama-africa.jpg',
            'phone' => '+237 677 200 002',
            'email' => 'mama@africa-food.cm',
            'address' => 'Carrefour Total, Bafoussam',
            'latitude' => 5.4780,
            'longitude' => 10.4200,
            'city' => 'Bafoussam',
            'delivery_fee' => 500,
            'delivery_time_min' => 25,
            'delivery_time_max' => 45,
            'minimum_order' => 1500,
            'rating' => 4.3,
            'ratings_count' => 85,
            'is_active' => true,
            'is_featured' => true,
            'is_open' => true,
            'opening_hours' => [
                'mon' => ['open' => '08:00', 'close' => '21:00'],
                'tue' => ['open' => '08:00', 'close' => '21:00'],
                'wed' => ['open' => '08:00', 'close' => '21:00'],
                'thu' => ['open' => '08:00', 'close' => '21:00'],
                'fri' => ['open' => '08:00', 'close' => '22:00'],
                'sat' => ['open' => '08:00', 'close' => '22:00'],
                'sun' => ['open' => '10:00', 'close' => '20:00'],
            ],
        ]);
        $this->seedMenu($r2, [
            ['category' => 'Petit Déjeuner', 'items' => [
                ['name' => 'Beignets Haricots', 'price' => 500, 'description' => 'Beignets soufflés avec sauce haricots', 'image' => 'menu-items/beignets-haricots.jpg'],
                ['name' => 'Omelette Garnie', 'price' => 1000, 'description' => 'Omelette aux légumes, pain beurré', 'image' => 'menu-items/omelette-garnie.jpg'],
            ]],
            ['category' => 'Plats du Jour', 'items' => [
                ['name' => 'Ndolé Plantain', 'price' => 2500, 'description' => 'Ndolé maison, plantain mûr, crevettes', 'image' => 'menu-items/ndole-complet.jpg'],
                ['name' => 'Couscous de Maïs', 'price' => 2000, 'description' => 'Couscous maïs, sauce gombo, viande', 'image' => 'menu-items/couscous-legumes.jpg'],
                ['name' => 'Poisson Braisé', 'price' => 3500, 'description' => 'Poisson frais braisé, condiments épicés', 'image' => 'menu-items/poisson-braise.jpg'],
            ]],
            ['category' => 'Boissons', 'items' => [
                ['name' => 'Smoothie Mangue', 'price' => 800, 'description' => 'Mangue fraîche mixée, lait de coco', 'image' => 'menu-items/smoothie-mangue.jpg'],
                ['name' => 'Jus de Gingembre', 'price' => 500, 'description' => 'Gingembre frais, citron vert', 'image' => 'menu-items/jus-gingembre.jpg'],
            ]],
        ]);

        // ============================================================
        // RESTAURANT 3 — La Braise Dorée
        // ============================================================
        $r3 = Restaurant::firstOrCreate(['slug' => 'la-braise-doree'], [
            'category_id' => $catBraise->id,
            'name' => 'La Braise Dorée',
            'description' => 'Spécialiste du poisson et poulet braisé depuis 2010.',
            'logo' => 'restaurants/logos/la-braise-doree.jpg',
            'cover_image' => 'restaurants/covers/la-braise-doree.jpg',
            'phone' => '+237 655 300 003',
            'email' => 'labraise@doree.cm',
            'address' => 'Quartier Djeleng, Bafoussam',
            'latitude' => 5.4690,
            'longitude' => 10.4100,
            'city' => 'Bafoussam',
            'delivery_fee' => 500,
            'delivery_time_min' => 30,
            'delivery_time_max' => 50,
            'minimum_order' => 2500,
            'rating' => 4.7,
            'ratings_count' => 200,
            'is_active' => true,
            'is_featured' => true,
            'is_open' => true,
            'opening_hours' => [
                'mon' => ['open' => '12:00', 'close' => '23:00'],
                'tue' => ['open' => '12:00', 'close' => '23:00'],
                'wed' => ['open' => '12:00', 'close' => '23:00'],
                'thu' => ['open' => '12:00', 'close' => '23:00'],
                'fri' => ['open' => '12:00', 'close' => '00:00'],
                'sat' => ['open' => '12:00', 'close' => '00:00'],
                'sun' => ['open' => '12:00', 'close' => '22:00'],
            ],
        ]);
        $this->seedMenu($r3, [
            ['category' => 'Braisés', 'items' => [
                ['name' => 'Poulet Braisé Entier', 'price' => 5000, 'description' => 'Poulet entier mariné et braisé au feu de bois', 'image' => 'menu-items/poulet-braise.jpg'],
                ['name' => 'Poisson Braisé', 'price' => 4500, 'description' => 'Bar ou machoiron braisé, sauce piment', 'image' => 'menu-items/poisson-braise.jpg'],
                ['name' => 'Brochettes de Boeuf x6', 'price' => 3000, 'description' => 'Boeuf tendre mariné aux épices', 'image' => 'menu-items/brochettes-boeuf.jpg'],
            ]],
            ['category' => 'Accompagnements', 'items' => [
                ['name' => 'Plantain Braisé', 'price' => 500, 'description' => 'Plantain mûr grillé', 'image' => 'menu-items/plantain-grille.jpg'],
                ['name' => 'Bâtons de Manioc', 'price' => 300, 'description' => 'Bâtons de manioc traditionnels', 'image' => 'menu-items/taro-sauce.jpg'],
            ]],
            ['category' => 'Boissons', 'items' => [
                ['name' => 'Bière 33 Export', 'price' => 800, 'description' => 'Bière 33cl bien fraîche', 'image' => 'menu-items/biere-locale.jpg'],
                ['name' => 'Coca-Cola 50cl', 'price' => 500, 'description' => '', 'image' => 'menu-items/jus-gingembre.jpg'],
            ]],
        ]);

        // ============================================================
        // RESTAURANT 4 — Saveurs du Cameroun
        // ============================================================
        $r4 = Restaurant::firstOrCreate(['slug' => 'saveurs-du-cameroun'], [
            'category_id' => $catRestaurant->id,
            'name' => 'Saveurs du Cameroun',
            'description' => 'Un voyage culinaire à travers les 10 régions du Cameroun.',
            'logo' => 'restaurants/logos/saveurs-du-cameroun.jpg',
            'cover_image' => 'restaurants/covers/saveurs-du-cameroun.jpg',
            'phone' => '+237 690 400 004',
            'email' => 'info@saveurscameroun.cm',
            'address' => 'Avenue Wanko, Bafoussam',
            'latitude' => 5.4810,
            'longitude' => 10.4230,
            'city' => 'Bafoussam',
            'delivery_fee' => 500,
            'delivery_time_min' => 20,
            'delivery_time_max' => 35,
            'minimum_order' => 2000,
            'rating' => 4.2,
            'ratings_count' => 65,
            'is_active' => true,
            'is_featured' => false,
            'is_open' => true,
            'opening_hours' => [
                'mon' => ['open' => '09:00', 'close' => '22:00'],
                'tue' => ['open' => '09:00', 'close' => '22:00'],
                'wed' => ['open' => '09:00', 'close' => '22:00'],
                'thu' => ['open' => '09:00', 'close' => '22:00'],
                'fri' => ['open' => '09:00', 'close' => '23:00'],
                'sat' => ['open' => '09:00', 'close' => '23:00'],
                'sun' => ['open' => '11:00', 'close' => '21:00'],
            ],
        ]);
        $this->seedMenu($r4, [
            ['category' => 'Spécialités Régionales', 'items' => [
                ['name' => 'Thieboudienne', 'price' => 3000, 'description' => 'Riz au poisson à la sénégalaise, revisité camerounais', 'image' => 'menu-items/thieboudienne.jpg'],
                ['name' => 'Soupe de Poisson', 'price' => 2500, 'description' => 'Soupe épicée aux fruits de mer', 'image' => 'menu-items/soupe-poisson.jpg'],
                ['name' => 'Eru Spécial', 'price' => 3000, 'description' => 'Eru frais, crayfish, viande fumée, waterfoufou', 'image' => 'menu-items/eru-combo.jpg'],
            ]],
            ['category' => 'Grillades', 'items' => [
                ['name' => 'Poulet DG', 'price' => 4000, 'description' => 'Poulet Directeur Général, plantain frit, légumes sautés', 'image' => 'menu-items/poulet-braise.jpg'],
                ['name' => 'Brochettes Mixtes', 'price' => 2500, 'description' => 'Boeuf et poulet en brochettes épicées', 'image' => 'menu-items/brochettes-boeuf.jpg'],
            ]],
            ['category' => 'Desserts & Boissons', 'items' => [
                ['name' => 'Gâteau de Pistache', 'price' => 1000, 'description' => 'Gâteau traditionnel aux pistaches', 'image' => 'menu-items/beignets-haricots.jpg'],
                ['name' => 'Jus Foléré', 'price' => 500, 'description' => 'Jus d\'oseille de Guinée rafraîchissant', 'image' => 'menu-items/jus-gingembre.jpg'],
            ]],
        ]);

        // ============================================================
        // RESTAURANT 5 — Le Maquis Central
        // ============================================================
        $r5 = Restaurant::firstOrCreate(['slug' => 'le-maquis-central'], [
            'category_id' => $catBraise->id,
            'name' => 'Le Maquis Central',
            'description' => 'Ambiance maquis, bonne musique et bonne bouffe !',
            'logo' => 'restaurants/logos/le-maquis-central.jpg',
            'cover_image' => 'restaurants/covers/le-maquis-central.jpg',
            'phone' => '+237 677 500 005',
            'email' => 'maquis@central.cm',
            'address' => 'Marché A, Bafoussam',
            'latitude' => 5.4760,
            'longitude' => 10.4150,
            'city' => 'Bafoussam',
            'delivery_fee' => 300,
            'delivery_time_min' => 15,
            'delivery_time_max' => 30,
            'minimum_order' => 1500,
            'rating' => 4.0,
            'ratings_count' => 45,
            'is_active' => true,
            'is_featured' => false,
            'is_open' => true,
            'opening_hours' => [
                'mon' => ['open' => '11:00', 'close' => '23:00'],
                'tue' => ['open' => '11:00', 'close' => '23:00'],
                'wed' => ['open' => '11:00', 'close' => '23:00'],
                'thu' => ['open' => '11:00', 'close' => '23:00'],
                'fri' => ['open' => '11:00', 'close' => '00:00'],
                'sat' => ['open' => '11:00', 'close' => '00:00'],
                'sun' => ['open' => '12:00', 'close' => '22:00'],
            ],
        ]);
        $this->seedMenu($r5, [
            ['category' => 'Grillades', 'items' => [
                ['name' => 'Poulet Braisé Demi', 'price' => 2500, 'description' => 'Demi-poulet braisé aux épices', 'image' => 'menu-items/poulet-braise.jpg'],
                ['name' => 'Poisson Grillé', 'price' => 3500, 'description' => 'Poisson du jour grillé au charbon', 'image' => 'menu-items/poisson-braise.jpg'],
                ['name' => 'Soya (Brochettes) x10', 'price' => 2000, 'description' => 'Brochettes de boeuf à la camerounaise', 'image' => 'menu-items/brochettes-boeuf.jpg'],
            ]],
            ['category' => 'Plats', 'items' => [
                ['name' => 'Riz Sauté Spécial', 'price' => 2000, 'description' => 'Riz sauté crevettes, légumes, oeuf', 'image' => 'menu-items/riz-sautee.jpg'],
                ['name' => 'Spaghetti Sautés', 'price' => 1500, 'description' => 'Spaghetti sautés aux légumes et viande', 'image' => 'menu-items/spaghetti-sautees.jpg'],
            ]],
            ['category' => 'Boissons', 'items' => [
                ['name' => 'Kadji Beer', 'price' => 700, 'description' => 'Bière locale 60cl', 'image' => 'menu-items/biere-locale.jpg'],
                ['name' => 'Eau Minérale 1.5L', 'price' => 500, 'description' => '', 'image' => 'menu-items/jus-gingembre.jpg'],
            ]],
        ]);

        // ============================================================
        // RESTAURANT 6 — Pizza Express Baf
        // ============================================================
        $r6 = Restaurant::firstOrCreate(['slug' => 'pizza-express-baf'], [
            'category_id' => $catPizza->id,
            'name' => 'Pizza Express Baf',
            'description' => 'Pizzas artisanales cuites au feu de bois.',
            'logo' => 'restaurants/logos/pizza-express-baf.jpg',
            'cover_image' => 'restaurants/covers/pizza-express-baf.jpg',
            'phone' => '+237 699 600 006',
            'email' => 'pizza@expressbaf.cm',
            'address' => 'Rue Commerciale, Bafoussam',
            'latitude' => 5.4750,
            'longitude' => 10.4190,
            'city' => 'Bafoussam',
            'delivery_fee' => 500,
            'delivery_time_min' => 25,
            'delivery_time_max' => 40,
            'minimum_order' => 3000,
            'rating' => 4.4,
            'ratings_count' => 95,
            'is_active' => true,
            'is_featured' => true,
            'is_open' => true,
            'opening_hours' => [
                'mon' => ['open' => '11:00', 'close' => '22:00'],
                'tue' => ['open' => '11:00', 'close' => '22:00'],
                'wed' => ['open' => '11:00', 'close' => '22:00'],
                'thu' => ['open' => '11:00', 'close' => '22:00'],
                'fri' => ['open' => '11:00', 'close' => '23:00'],
                'sat' => ['open' => '11:00', 'close' => '23:00'],
                'sun' => ['open' => '12:00', 'close' => '21:00'],
            ],
        ]);
        $this->seedMenu($r6, [
            ['category' => 'Pizzas Classiques', 'items' => [
                ['name' => 'Margherita', 'price' => 3500, 'description' => 'Tomate, mozzarella, basilic frais', 'image' => 'menu-items/poulet-braise.jpg'],
                ['name' => 'Pizza Poulet BBQ', 'price' => 4500, 'description' => 'Poulet grillé, sauce BBQ, oignons caramélisés', 'image' => 'menu-items/poulet-braise.jpg'],
                ['name' => 'Pizza 4 Fromages', 'price' => 5000, 'description' => 'Mozzarella, cheddar, emmental, chèvre', 'image' => 'menu-items/taro-sauce.jpg'],
            ]],
            ['category' => 'Pizzas Spéciales', 'items' => [
                ['name' => 'Pizza Ndolé', 'price' => 5500, 'description' => 'Base ndolé, crevettes, fromage fondu — fusion camerounaise !', 'image' => 'menu-items/ndole-complet.jpg'],
                ['name' => 'Pizza Viande Fumée', 'price' => 5000, 'description' => 'Viande fumée, poivrons, oignons', 'image' => 'menu-items/brochettes-boeuf.jpg'],
            ]],
            ['category' => 'Boissons', 'items' => [
                ['name' => 'Coca-Cola 33cl', 'price' => 400, 'description' => '', 'image' => 'menu-items/jus-gingembre.jpg'],
                ['name' => 'Fanta Orange', 'price' => 400, 'description' => '', 'image' => 'menu-items/smoothie-mangue.jpg'],
            ]],
        ]);

        // ============================================================
        // RESTAURANT 7 — Le Coin des Jus
        // ============================================================
        $r7 = Restaurant::firstOrCreate(['slug' => 'le-coin-des-jus'], [
            'category_id' => $catJus->id,
            'name' => 'Le Coin des Jus',
            'description' => 'Jus naturels, smoothies et salades fraîches.',
            'logo' => 'restaurants/logos/le-coin-des-jus.jpg',
            'cover_image' => 'restaurants/covers/le-coin-des-jus.jpg',
            'phone' => '+237 655 700 007',
            'email' => 'jus@coin.cm',
            'address' => 'Face Hôpital, Bafoussam',
            'latitude' => 5.4700,
            'longitude' => 10.4120,
            'city' => 'Bafoussam',
            'delivery_fee' => 300,
            'delivery_time_min' => 10,
            'delivery_time_max' => 25,
            'minimum_order' => 1000,
            'rating' => 4.6,
            'ratings_count' => 150,
            'is_active' => true,
            'is_featured' => true,
            'is_open' => true,
            'opening_hours' => [
                'mon' => ['open' => '07:00', 'close' => '20:00'],
                'tue' => ['open' => '07:00', 'close' => '20:00'],
                'wed' => ['open' => '07:00', 'close' => '20:00'],
                'thu' => ['open' => '07:00', 'close' => '20:00'],
                'fri' => ['open' => '07:00', 'close' => '20:00'],
                'sat' => ['open' => '08:00', 'close' => '20:00'],
                'sun' => ['open' => '08:00', 'close' => '18:00'],
            ],
        ]);
        $this->seedMenu($r7, [
            ['category' => 'Jus Naturels', 'items' => [
                ['name' => 'Jus de Gingembre', 'price' => 500, 'description' => 'Gingembre, citron, miel, menthe', 'image' => 'menu-items/jus-gingembre.jpg'],
                ['name' => 'Jus d\'Ananas', 'price' => 600, 'description' => 'Ananas frais pressé à la minute', 'image' => 'menu-items/smoothie-mangue.jpg'],
                ['name' => 'Jus Foléré', 'price' => 500, 'description' => 'Bissap / oseille de Guinée', 'image' => 'menu-items/jus-gingembre.jpg'],
                ['name' => 'Jus Cocktail Détox', 'price' => 800, 'description' => 'Concombre, citron, gingembre, menthe', 'image' => 'menu-items/jus-gingembre.jpg'],
            ]],
            ['category' => 'Smoothies', 'items' => [
                ['name' => 'Smoothie Mangue-Coco', 'price' => 1000, 'description' => 'Mangue, lait de coco, banane', 'image' => 'menu-items/smoothie-mangue.jpg'],
                ['name' => 'Smoothie Banane-Avoine', 'price' => 900, 'description' => 'Banane, avoine, miel, lait', 'image' => 'menu-items/smoothie-mangue.jpg'],
            ]],
            ['category' => 'Salades', 'items' => [
                ['name' => 'Salade Tropicale', 'price' => 1500, 'description' => 'Avocat, mangue, poulet grillé, vinaigrette passion', 'image' => 'menu-items/salade-tropicale.jpg'],
                ['name' => 'Salade César', 'price' => 1800, 'description' => 'Romaine, poulet, croutons, parmesan', 'image' => 'menu-items/salade-tropicale.jpg'],
            ]],
        ]);

        // ============================================================
        // RESTAURANT 8 — Grill House 237
        // ============================================================
        $r8 = Restaurant::firstOrCreate(['slug' => 'grill-house-237'], [
            'category_id' => $catFastFood->id,
            'name' => 'Grill House 237',
            'description' => 'Burgers, wraps et grillades rapides. Le fast-food camerounais !',
            'logo' => 'restaurants/logos/grill-house-237.jpg',
            'cover_image' => 'restaurants/covers/grill-house-237.jpg',
            'phone' => '+237 690 800 008',
            'email' => 'grill@house237.cm',
            'address' => 'Rond-point Familiar, Bafoussam',
            'latitude' => 5.4720,
            'longitude' => 10.4160,
            'city' => 'Bafoussam',
            'delivery_fee' => 500,
            'delivery_time_min' => 15,
            'delivery_time_max' => 30,
            'minimum_order' => 1500,
            'rating' => 4.1,
            'ratings_count' => 70,
            'is_active' => true,
            'is_featured' => false,
            'is_open' => true,
            'opening_hours' => [
                'mon' => ['open' => '10:00', 'close' => '22:00'],
                'tue' => ['open' => '10:00', 'close' => '22:00'],
                'wed' => ['open' => '10:00', 'close' => '22:00'],
                'thu' => ['open' => '10:00', 'close' => '22:00'],
                'fri' => ['open' => '10:00', 'close' => '23:00'],
                'sat' => ['open' => '10:00', 'close' => '23:00'],
                'sun' => ['open' => '11:00', 'close' => '21:00'],
            ],
        ]);
        $this->seedMenu($r8, [
            ['category' => 'Burgers', 'items' => [
                ['name' => 'Classic Burger', 'price' => 2500, 'description' => 'Steak boeuf, laitue, tomate, oignon, ketchup', 'image' => 'menu-items/poulet-braise.jpg'],
                ['name' => 'Cheese Burger', 'price' => 3000, 'description' => 'Double fromage fondu, bacon croustillant', 'image' => 'menu-items/poulet-braise.jpg'],
                ['name' => 'Burger Suya', 'price' => 3500, 'description' => 'Viande suya épicée, oignons, sauce piment', 'image' => 'menu-items/brochettes-boeuf.jpg'],
            ]],
            ['category' => 'Wraps & Tacos', 'items' => [
                ['name' => 'Wrap Poulet Grillé', 'price' => 2000, 'description' => 'Poulet, crudités, sauce mayo-piment', 'image' => 'menu-items/wrap-poulet.jpg'],
                ['name' => 'Tacos Viande Hachée', 'price' => 2200, 'description' => 'Viande hachée épicée, fromage, salade', 'image' => 'menu-items/wrap-poulet.jpg'],
            ]],
            ['category' => 'Accompagnements', 'items' => [
                ['name' => 'Frites Maison', 'price' => 800, 'description' => 'Pommes de terre fraîches coupées et frites', 'image' => 'menu-items/plantain-grille.jpg'],
                ['name' => 'Nuggets x8', 'price' => 1500, 'description' => 'Poulet pané croustillant, sauce BBQ', 'image' => 'menu-items/poulet-braise.jpg'],
            ]],
        ]);

        // ============================================================
        // RESTAURANT 9 — La Table Royale
        // ============================================================
        $r9 = Restaurant::firstOrCreate(['slug' => 'la-table-royale'], [
            'category_id' => $catRestaurant->id,
            'name' => 'La Table Royale',
            'description' => 'Gastronomie camerounaise haut de gamme pour les grandes occasions.',
            'logo' => 'restaurants/logos/la-table-royale.jpg',
            'cover_image' => 'restaurants/covers/la-table-royale.jpg',
            'phone' => '+237 677 900 009',
            'email' => 'reservation@tableroyale.cm',
            'address' => 'Quartier Administratif, Bafoussam',
            'latitude' => 5.4800,
            'longitude' => 10.4250,
            'city' => 'Bafoussam',
            'delivery_fee' => 1000,
            'delivery_time_min' => 30,
            'delivery_time_max' => 50,
            'minimum_order' => 5000,
            'rating' => 4.8,
            'ratings_count' => 55,
            'is_active' => true,
            'is_featured' => true,
            'is_open' => true,
            'opening_hours' => [
                'mon' => ['open' => '11:00', 'close' => '22:00'],
                'tue' => ['open' => '11:00', 'close' => '22:00'],
                'wed' => ['open' => '11:00', 'close' => '22:00'],
                'thu' => ['open' => '11:00', 'close' => '22:00'],
                'fri' => ['open' => '11:00', 'close' => '23:00'],
                'sat' => ['open' => '11:00', 'close' => '23:00'],
                'sun' => ['open' => '12:00', 'close' => '21:00'],
            ],
        ]);
        $this->seedMenu($r9, [
            ['category' => 'Entrées', 'items' => [
                ['name' => 'Salade de Crabe', 'price' => 3000, 'description' => 'Crabe frais, avocat, mangue verte, vinaigrette agrumes', 'image' => 'menu-items/salade-tropicale.jpg'],
                ['name' => 'Soupe de Poisson Royale', 'price' => 2500, 'description' => 'Bisque de poisson, crevettes, croûtons', 'image' => 'menu-items/soupe-poisson.jpg'],
            ]],
            ['category' => 'Plats Signature', 'items' => [
                ['name' => 'Ndolé Royal', 'price' => 5500, 'description' => 'Ndolé aux gambas, plantain caramélisé, riz parfumé', 'image' => 'menu-items/ndole-complet.jpg'],
                ['name' => 'Filet de Capitaine', 'price' => 6000, 'description' => 'Filet de capitaine grillé, purée de patate douce, sauce citronnée', 'image' => 'menu-items/poisson-braise.jpg'],
                ['name' => 'Côte de Boeuf 500g', 'price' => 8000, 'description' => 'Côte de boeuf grillée, frites maison, salade', 'image' => 'menu-items/brochettes-boeuf.jpg'],
                ['name' => 'Poulet DG Premium', 'price' => 5000, 'description' => 'Poulet DG revisité, légumes de saison', 'image' => 'menu-items/poulet-braise.jpg'],
            ]],
            ['category' => 'Desserts', 'items' => [
                ['name' => 'Fondant au Chocolat', 'price' => 2000, 'description' => 'Coeur coulant, glace vanille', 'image' => 'menu-items/beignets-haricots.jpg'],
                ['name' => 'Crème Brûlée Coco', 'price' => 1800, 'description' => 'Crème à la noix de coco, caramel craquant', 'image' => 'menu-items/smoothie-mangue.jpg'],
            ]],
            ['category' => 'Vins & Cocktails', 'items' => [
                ['name' => 'Vin Rouge (verre)', 'price' => 2000, 'description' => 'Sélection du sommelier', 'image' => 'menu-items/biere-locale.jpg'],
                ['name' => 'Cocktail Passion', 'price' => 2500, 'description' => 'Fruit de la passion, rhum, citron vert', 'image' => 'menu-items/smoothie-mangue.jpg'],
            ]],
        ]);

        $this->command->info('9 restaurants avec menus créés avec succès !');
    }

    /**
     * Helper pour créer les catégories et articles d'un restaurant.
     * Utilise firstOrCreate pour ne pas dupliquer.
     */
    private function seedMenu(Restaurant $restaurant, array $menu): void
    {
        foreach ($menu as $i => $catData) {
            $cat = MenuCategory::firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $catData['category']],
                [
                    'slug'       => Str::slug($catData['category']),
                    'is_active'  => true,
                    'sort_order' => $i,
                ]
            );

            foreach ($catData['items'] as $j => $item) {
                MenuItem::firstOrCreate(
                    ['menu_category_id' => $cat->id, 'name' => $item['name']],
                    [
                        'restaurant_id'    => $restaurant->id,
                        'description'      => $item['description'] ?? '',
                        'price'            => $item['price'],
                        'image'            => $item['image'] ?? null,
                        'is_available'     => true,
                        'preparation_time' => 15,
                        'sort_order'       => $j,
                    ]
                );
            }
        }
    }
}
