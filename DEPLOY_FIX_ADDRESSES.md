# Fix: Server Error lors de la sauvegarde d'adresses

## Problème
L'application renvoie "Server Error" quand on essaie de sauvegarder une adresse sans coordonnées GPS.

## Cause
Les colonnes `latitude`, `longitude`, et `city` dans la table `addresses` sont NOT NULL, mais le contrôleur API les accepte comme nullable.

## Solution
Migration créée pour rendre ces colonnes nullable.

## Déploiement sur Production

### Étape 1: Pousser les changements sur Git
```bash
cd /Users/redwolf-dark/Documents/restaurant-delivery-backend
git add .
git commit -m "Fix: Make coordinates nullable in addresses table"
git push origin main
```

### Étape 2: Sur le serveur de production
Connecte-toi au serveur et exécute:

```bash
cd /path/to/restaurant-delivery-backend
git pull origin main
php artisan migrate --force
```

### Étape 3: Vérifier que ça fonctionne
Teste l'ajout d'une adresse depuis l'application mobile.

## Fichiers modifiés
- `database/migrations/2026_02_19_080652_create_addresses_table.php` (migration originale)
- `database/migrations/2026_02_21_154811_make_coordinates_nullable_in_addresses_table.php` (nouvelle migration)

## Alternative: Migration manuelle SQL
Si tu préfères exécuter directement SQL sur le serveur:

```sql
ALTER TABLE addresses
MODIFY COLUMN latitude DECIMAL(10,8) NULL,
MODIFY COLUMN longitude DECIMAL(11,8) NULL,
MODIFY COLUMN city VARCHAR(255) NULL;
```
