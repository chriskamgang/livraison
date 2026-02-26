<?php

namespace App\Notifications;

use App\Models\AccountDeletionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $deletionRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(AccountDeletionRequest $deletionRequest)
    {
        $this->deletionRequest = $deletionRequest;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirmation de demande de suppression de compte - ChillOut')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Nous avons bien reçu votre demande de suppression de compte.')
            ->line('Votre demande est actuellement en cours de traitement par notre équipe.')
            ->line('**Détails de votre demande :**')
            ->line('- **Date de demande :** ' . $this->deletionRequest->created_at->format('d/m/Y à H:i'))
            ->line('- **Statut :** En attente de traitement')
            ->line('- **Numéro de demande :** #' . $this->deletionRequest->id)
            ->line('Conformément à notre politique de confidentialité, votre compte et toutes vos données associées seront supprimés dans un délai de **7 jours ouvrables**.')
            ->line('**Ce qui sera supprimé :**')
            ->line('• Vos informations personnelles')
            ->line('• Votre historique de commandes')
            ->line('• Vos adresses de livraison')
            ->line('• Vos favoris et préférences')
            ->line('• Vos méthodes de paiement')
            ->line('Si vous avez changé d\'avis ou si vous n\'êtes pas à l\'origine de cette demande, veuillez nous contacter immédiatement à support@myinsam.cm.')
            ->line('Nous sommes désolés de vous voir partir et nous vous remercions d\'avoir utilisé ChillOut.')
            ->salutation('Cordialement, L\'équipe ChillOut');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'deletion_request_id' => $this->deletionRequest->id,
            'status' => $this->deletionRequest->status,
        ];
    }
}
