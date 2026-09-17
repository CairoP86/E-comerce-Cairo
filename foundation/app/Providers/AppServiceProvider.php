<?php

namespace App\Providers;

use App\Contracts\CartStore;
use App\Enums\Role;
use App\Models\User;
use App\Services\SessionCartStore;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CartStore::class, SessionCartStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Verifica tu correo electrónico')->greeting('Hola, '.$notifiable->name)
            ->line('Confirma tu correo para acceder a tu cuenta.')->action('Verificar correo', $url)
            ->line('Si no creaste esta cuenta, puedes ignorar este mensaje.'));
        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject('Recupera el acceso a tu cuenta')->greeting('Hola')
            ->line('Recibimos una solicitud para restablecer tu contraseña.')
            ->action('Elegir nueva contraseña', route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]))
            ->line('El enlace vence en '.config('auth.passwords.users.expire').' minutos. Si no lo solicitaste, ignora este mensaje.'));
        Gate::define('access-operations', fn (User $user) => in_array($user->role, [Role::Operator, Role::Admin], true));
        Gate::define('view-audit', fn (User $user) => $user->role === Role::Admin);
        Gate::define('view-catalog-admin', fn (User $user) => in_array($user->role, [Role::Operator, Role::Admin], true));
        Gate::define('manage-catalog', fn (User $user) => $user->role === Role::Admin);
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-account:'.hash('sha256', strtolower(trim((string) $request->input('email'))).'|'.$request->ip())),
        ]);
        RateLimiter::for('auth-actions', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        Event::listen(Login::class, fn (Login $event) => Audit::record('auth.login', $event->user->id, $event->user->id));
        Event::listen(Failed::class, fn (Failed $event) => Audit::record('auth.login_failed'));
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                Audit::record('auth.logout', $event->user->id, $event->user->id);
            }
        });
        Event::listen(Verified::class, fn (Verified $event) => Audit::record('auth.email_verified', $event->user->id, $event->user->id));
    }
}
