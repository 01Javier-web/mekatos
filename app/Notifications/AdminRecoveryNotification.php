<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enlace de recuperación de emergencia para el único ADMIN funcional bloqueado.
 * Se envía únicamente al correo configurado en ADMIN_RECOVERY_EMAIL.
 */
class AdminRecoveryNotification extends Notification
{
    public function __construct(
        public readonly string $adminEmail,
        public readonly string $url,
        public readonly int $expiresInMinutes,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Mekatos: recuperación de acceso de administrador')
            ->line("La cuenta de administrador {$this->adminEmail} quedó bloqueada por 3 intentos fallidos de inicio de sesión y no hay otro administrador disponible para desbloquearla.")
            ->line('Usa el siguiente enlace para desbloquearla y definir una contraseña nueva:')
            ->action('Recuperar acceso', $this->url)
            ->line("El enlace sirve una sola vez y vence en {$this->expiresInMinutes} minutos.")
            ->line('Si tú no intentaste iniciar sesión, alguien pudo intentarlo con esa cuenta. No reenvíes este correo.');
    }
}
