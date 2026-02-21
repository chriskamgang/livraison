# 📱 Notifications WhatsApp - Guide de Configuration

Ce système envoie automatiquement des notifications WhatsApp aux clients et livreurs via l'API UltraMsg.

## 🎯 Notifications Implémentées

### Pour le Client :
1. ✅ **Commande reçue** - Quand il passe une commande
2. ✅ **Commande en préparation** - Quand le restaurant prépare
3. ✅ **Commande prête** - Quand le plat est prêt
4. ✅ **Livreur en route** - Quand le livreur a récupéré la commande
5. ✅ **Commande livrée** - Quand la livraison est terminée
6. ✅ **Commande annulée** - En cas d'annulation

### Pour le Livreur :
1. ✅ **Livraison assignée** - Quand une commande lui est attribuée

## 📋 Configuration

### Étape 1 : Créer un compte UltraMsg

1. Allez sur https://ultramsg.com
2. Créez un compte gratuit
3. Créez une instance WhatsApp
4. Scannez le QR code avec votre WhatsApp Business

### Étape 2 : Récupérer vos credentials

Dans le dashboard UltraMsg, vous trouverez :
- **Instance ID** : ex: `instance1150`
- **Token** : votre token d'API

### Étape 3 : Configurer Laravel

Ajoutez ces variables dans votre fichier `.env` :

```env
ULTRAMSG_INSTANCE_ID=instance1150
ULTRAMSG_TOKEN=votre_token_ici
```

### Étape 4 : Format des numéros

Les numéros de téléphone doivent être au format international :
- ✅ Bon : `+237690000000`
- ✅ Bon : `237690000000` (le + sera ajouté automatiquement)
- ❌ Mauvais : `0690000000`

## 🔧 Utilisation

Le service WhatsApp est automatiquement appelé dans les controllers :

### Exemple 1 : Notification à la création de commande

```php
use App\Services\WhatsAppService;

$whatsappService = new WhatsAppService();
$whatsappService->notifyOrderReceived($order, $customer);
```

### Exemple 2 : Notification livreur assigné

```php
$whatsappService->notifyDeliveryAssigned($order, $driver);
```

### Exemple 3 : Notification livreur en route

```php
$whatsappService->notifyDriverEnRoute($order, $driver, $customer);
```

## 📝 Personnaliser les messages

Éditez le fichier `app/Services/WhatsAppService.php` pour modifier les templates de messages.

Exemple de message :

```php
$message = "🎉 *Commande reçue avec succès !*\n\n";
$message .= "Bonjour {$customer->name},\n\n";
$message .= "Votre commande #{$order->id} a bien été enregistrée.\n\n";
```

## 🧪 Tester les notifications

```php
// Dans tinker ou un controller de test
$user = User::find(1);
$order = Order::with('restaurant')->find(1);

$whatsapp = new \App\Services\WhatsAppService();
$whatsapp->notifyOrderReceived($order, $user);
```

## 🚨 Gestion des erreurs

Les erreurs sont loggées automatiquement :

```php
try {
    $whatsappService->notifyOrderReceived($order, $customer);
} catch (\Exception $e) {
    \Log::error('WhatsApp notification failed', ['error' => $e->getMessage()]);
}
```

Vérifiez les logs dans `storage/logs/laravel.log`

## 💰 Limites et Prix UltraMsg

- **Plan Gratuit** : 1000 messages/mois
- **Plans payants** : À partir de $10/mois pour plus de messages
- Consultez https://ultramsg.com/pricing pour plus d'infos

## 📊 Suivre les envois

Dans le dashboard UltraMsg, vous pouvez :
- Voir l'historique des messages envoyés
- Vérifier le statut de délivrance
- Consulter les erreurs

## 🔐 Sécurité

- Ne commitez JAMAIS votre token dans Git
- Utilisez `.env` pour stocker les credentials
- Le fichier `.env` est déjà dans `.gitignore`

## 🎨 Émojis disponibles

Les émojis fonctionnent nativement :
- 🎉 🚚 📍 💰 ✅ ❌ 👨‍🍳 📦 😋 🙏 🛵 📞 ⏱️ 🌟

## 📞 Support

- Documentation UltraMsg : https://docs.ultramsg.com
- En cas de problème : support@ultramsg.com
