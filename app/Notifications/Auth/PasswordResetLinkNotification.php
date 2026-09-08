<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Correo de restablecer contraseña (español + host correcto).
 * En tenant: {slug}.{TENANT_ROOT_DOMAIN}. En plataforma: APP_URL.
 */
class PasswordResetLinkNotification extends Notification
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);
        $tenant = $notifiable->tenant_id
            ? Tenant::query()->find($notifiable->tenant_id)
            : null;

        $resetUrl = $this->buildResetUrl($notifiable, $tenant);
        $brand = $tenant
            ? trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug))
            : (string) config('app.name', 'SendSaaS');

        if ($brand === '') {
            $brand = (string) config('app.name', 'SendSaaS');
        }

        $expiresIn = Carbon::now()->addMinutes($expireMinutes);

        return (new MailMessage)
            ->subject(__('Restablece tu contraseña en :brand', ['brand' => $brand]))
            ->greeting(__('Hola :name,', ['name' => $notifiable->name ?: '']))
            ->line(__('Recibimos una solicitud para restablecer la contraseña de tu cuenta en :brand.', [
                'brand' => $brand,
            ]))
            ->action(__('Crear nueva contraseña'), $resetUrl)
            ->line(__('Este enlace expira el :expires (en :minutes minutos).', [
                'expires' => $expiresIn->isoFormat('LLLL'),
                'minutes' => $expireMinutes,
            ]))
            ->line(__('Si no fuiste tú, ignora este correo: tu contraseña actual no ha cambiado.'))
            ->salutation(__('— Equipo :brand', ['brand' => $brand]));
    }

    public function buildResetUrl(User $user, ?Tenant $tenant): string
    {
        $email = $user->getEmailForPasswordReset();
        $rootDomain = (string) config('tenant.root_domain', 'sendsaas.orvae.pe');
        $appUrl = (string) config('app.url', 'https://sendsaas.orvae.pe');
        $scheme = $tenant
            ? (string) config('orvae.tenant.scheme', parse_url($appUrl, PHP_URL_SCHEME) ?: 'https')
            : (parse_url($appUrl, PHP_URL_SCHEME) ?: 'https');

        $appPort = parse_url($appUrl, PHP_URL_PORT);
        $portSuffix = $appPort && ! in_array((int) $appPort, [80, 443], true)
            ? ':'.$appPort
            : '';

        $host = $tenant && filled($tenant->slug)
            ? $tenant->slug.'.'.$rootDomain.$portSuffix
            : (parse_url($appUrl, PHP_URL_HOST) ?: $rootDomain).$portSuffix;

        return $scheme.'://'.$host.'/reset-password/'.$this->token.'?'.http_build_query([
            'email' => $email,
        ]);
    }
}
