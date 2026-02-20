# Restaurant Delivery — Architecture Complète

## Structure des Projets

```
Documents/
├── restaurant-delivery-backend/   # Backend Laravel (port 8002)
├── restaurant-delivery-client/    # App Mobile Client (React Native + Expo)
└── restaurant-delivery-driver/    # App Mobile Livreur (React Native + Expo)
```

---

## Architecture Globale

```
┌─────────────────────────────────────────────────────────────┐
│                      BACKEND LARAVEL 12                      │
│         API REST (port 8002) + WebSocket Reverb (8080)       │
│              Base de données MySQL (restaurant_delivery)      │
└──────────┬──────────────────┬───────────────────┬───────────┘
           │                  │                   │
    ┌──────▼──────┐   ┌───────▼───────┐   ┌──────▼──────┐
    │  App Client │   │  App Livreur  │   │ Panel Admin  │
    │React Native │   │ React Native  │   │ Filament 3   │
    │    Expo     │   │     Expo      │   │ /admin       │
    └─────────────┘   └───────────────┘   └─────────────┘
```

---

## Technologies Utilisées

| Couche           | Technologie                  | Usage                        |
|------------------|------------------------------|------------------------------|
| Backend API      | Laravel 12 + Sanctum         | API REST + Auth par token    |
| Admin Dashboard  | Filament 3.2                 | Gestion complète             |
| Temps réel       | Laravel Reverb (WebSocket)   | Suivi commandes live         |
| App Client       | React Native + Expo          | iOS + Android                |
| App Livreur      | React Native + Expo          | iOS + Android                |
| Base de données  | MySQL (XAMPP port 3306)      | restaurant_delivery          |
| GPS Précis       | expo-location BestForNavigation | Précision 3-10m           |
| Cartes           | React Native Maps + Google   | Navigation + Suivi           |
| Push Notifs      | Expo + FCM                   | Alertes temps réel           |
| Paiements        | Notchpay / CinetPay          | MTN MoMo + Orange Money      |
| Images           | Laravel Storage              | Logos, photos, preuves       |

---

## Structure Base de Données

```
Tables
├── users                    # Clients + Livreurs + Admins
├── restaurant_categories    # Burger, Pizza, Poulet, etc.
├── restaurants              # Infos restaurant + GPS
├── menu_categories          # Catégories du menu
├── menu_items               # Plats avec prix et options
├── addresses                # Adresses sauvegardées clients
├── coupons                  # Codes promo
├── orders                   # Commandes
├── order_items              # Articles d'une commande
├── deliveries               # Livraisons + GPS précis
├── delivery_locations       # Historique GPS livreur (tracking)
├── payments                 # Paiements (MoMo, cash, etc.)
├── ratings                  # Notes resto + livreur
├── coupon_user              # Usage des coupons par user
├── notifications_custom     # Notifications in-app
└── personal_access_tokens   # Tokens Sanctum
```

---

## Endpoints API — Résumé

### Publics
| Méthode | Endpoint                    | Description              |
|---------|-----------------------------|--------------------------|
| POST    | /api/auth/register          | Inscription client       |
| POST    | /api/auth/login             | Connexion                |
| POST    | /api/auth/register/driver   | Inscription livreur      |
| GET     | /api/restaurants            | Liste restaurants        |
| GET     | /api/restaurants/featured   | Restaurants mis en avant |
| GET     | /api/restaurants/categories | Catégories               |
| GET     | /api/restaurants/{id}       | Détail restaurant        |
| GET     | /api/restaurants/{id}/menu  | Menu complet             |

### Protégés (auth:sanctum)
| Méthode | Endpoint                            | Description              |
|---------|-------------------------------------|--------------------------|
| GET     | /api/auth/me                        | Profil connecté          |
| POST    | /api/auth/logout                    | Déconnexion              |
| GET     | /api/orders                         | Mes commandes            |
| POST    | /api/orders                         | Passer une commande      |
| GET     | /api/orders/{id}/track              | Suivi temps réel         |
| POST    | /api/driver/toggle-online           | Activer/désactiver dispo |
| POST    | /api/driver/location                | Envoyer position GPS     |
| POST    | /api/driver/orders/{id}/accept      | Accepter une commande    |
| POST    | /api/driver/deliveries/{id}/proof   | Photo preuve livraison   |

---

## GPS Livreur — Précision Maximale

```javascript
// Configuration dans l'App Livreur
{
  accuracy: Location.Accuracy.BestForNavigation, // Mode le plus précis
  timeInterval: 3000,    // Toutes les 3 secondes
  distanceInterval: 5,   // Ou tous les 5 mètres
  mayShowUserSettingsDialog: true
}

// Flux de données
App Livreur (GPS) → API POST /driver/location → MySQL (delivery_locations)
                                              → WebSocket Reverb
                                              → App Client (carte mise à jour)
```

---

## Architecture App Client (React Native)

```
restaurant-delivery-client/
├── src/
│   ├── screens/
│   │   ├── auth/            # Login, Register, OTP
│   │   ├── home/            # Accueil avec restaurants
│   │   ├── restaurant/      # Détail + Menu
│   │   ├── cart/            # Panier
│   │   ├── checkout/        # Paiement
│   │   ├── tracking/        # Suivi commande live
│   │   ├── orders/          # Historique
│   │   ├── profile/         # Profil + Adresses
│   │   └── notifications/   # Notifications
│   ├── services/
│   │   ├── apiService.js    # Axios centralisé
│   │   ├── authService.js   # Auth
│   │   ├── orderService.js  # Commandes
│   │   └── trackingService.js # WebSocket GPS
│   ├── components/          # Composants réutilisables
│   ├── config/
│   │   └── api.js           # URL: http://localhost:8002/api
│   └── navigation/          # React Navigation
```

---

## Architecture App Livreur (React Native)

```
restaurant-delivery-driver/
├── src/
│   ├── screens/
│   │   ├── auth/            # Login livreur
│   │   ├── dashboard/       # Tableau de bord + toggle dispo
│   │   ├── order-request/   # Nouvelle commande (accepter/refuser)
│   │   ├── navigation/      # Carte + itinéraire actif
│   │   ├── history/         # Historique livraisons
│   │   └── earnings/        # Gains et portefeuille
│   ├── services/
│   │   ├── locationService.js    # GPS haute précision
│   │   ├── deliveryService.js    # API livraisons
│   │   └── notificationService.js # Push notifications
│   └── config/
│       └── api.js           # URL: http://10.0.2.2:8002/api (Android)
```

---

## Panel Admin (Filament 3) — Accès

```
URL: http://localhost:8002/admin
```

### Ressources Filament à créer
- [ ] RestaurantResource
- [ ] MenuItemResource
- [ ] OrderResource (avec suivi temps réel)
- [ ] UserResource (Clients + Livreurs)
- [ ] DeliveryResource (carte des livreurs)
- [ ] PaymentResource
- [ ] CouponResource
- [ ] RatingResource

---

## Ordre de Développement

### Phase 1 — Backend ✅
1. [x] Laravel 12 + MySQL configuré
2. [x] Migrations complètes (17 tables)
3. [x] Models Eloquent avec relations
4. [x] Routes API complètes
5. [x] AuthController (register, login, logout)
6. [ ] RestaurantController
7. [ ] OrderController
8. [ ] DeliveryController (GPS)
9. [ ] Filament Resources

### Phase 2 — App Client
10. [ ] Configuration API + navigation
11. [ ] Écrans Auth (Login/Register)
12. [ ] Écran Accueil (liste restaurants)
13. [ ] Écran Restaurant + Menu
14. [ ] Panier + Commande
15. [ ] Paiement (MoMo)
16. [ ] Suivi commande temps réel (WebSocket)
17. [ ] Profil + Adresses

### Phase 3 — App Livreur
18. [ ] Auth livreur
19. [ ] Dashboard + toggle disponibilité
20. [ ] GPS haute précision (background)
21. [ ] Accepter/refuser commandes
22. [ ] Navigation vers restaurant
23. [ ] Navigation vers client (GPS précis)
24. [ ] Photo preuve livraison
25. [ ] Gains et historique

### Phase 4 — Admin + Finalisation
26. [ ] Filament resources complètes
27. [ ] Carte temps réel des livreurs (admin)
28. [ ] Dashboard métriques
29. [ ] Tests + correction bugs
30. [ ] Déploiement

---

## Commandes Utiles

### Backend
```bash
# Démarrer le serveur
php artisan serve --port=8002

# Démarrer WebSocket Reverb
php artisan reverb:start

# Réinitialiser la base de données
php artisan migrate:fresh --seed

# Créer un admin Filament
php artisan make:filament-user
```

### App Client
```bash
cd ~/Documents/restaurant-delivery-client
npx expo start
npx expo start --ios
npx expo start --android
```

### App Livreur
```bash
cd ~/Documents/restaurant-delivery-driver
npx expo start
npx expo start --ios
```

---

## Configuration URLs API

| Plateforme          | URL Backend                     |
|---------------------|---------------------------------|
| iOS Simulator       | http://localhost:8002/api       |
| Android Emulator    | http://10.0.2.2:8002/api        |
| Appareil physique   | http://[IP_LOCAL]:8002/api      |

---
*Projet créé le 19/02/2026 — Restaurant Delivery App*
