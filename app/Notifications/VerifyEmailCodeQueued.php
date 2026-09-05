<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * E-mail com o código de 6 dígitos para verificação da conta.
 * Enfileirada: o envio SMTP nunca bloqueia a request de cadastro/reenvio.
 */
class VerifyEmailCodeQueued extends Notification implements ShouldQueue
{
    use Queueable;

    /** Tenta reenviar em caso de falha transitoria de SMTP. */
    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public string $code)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu código de verificação')
            ->greeting('Olá!')
            ->line('Use o código abaixo para confirmar seu e-mail:')
            ->line("**{$this->code}**")
            ->line('O código expira em 15 minutos.')
            ->line('Se você não criou uma conta, ignore este e-mail.');
    }

    /**
     * Chamado quando todas as tentativas falham — registra no log em vez de
     * o e-mail sumir silenciosamente na fila.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Falha ao enviar o código de verificação de e-mail', [
            'exception' => $e->getMessage(),
        ]);
    }
}