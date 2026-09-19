<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StockHolds;
use App\Support\Audit;
use App\Support\CartHolder;
use App\Support\PasswordRequirements;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => PasswordRequirements::rules(),
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data); // role is not fillable; database default is customer.
            Audit::record('auth.registered', $user->id, $user->id);

            return $user;
        });
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        if (! Auth::attempt(['email' => Str::lower(trim($data['email'])), 'password' => $data['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }
        $request->session()->regenerate();

        return redirect()->intended('/account');
    }

    public function logout(Request $request)
    {
        // The session cart is discarded below, so its local holds must not keep blocking stock.
        app(StockHolds::class)->releaseAll(CartHolder::current());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return redirect()->route('login');
    }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        Password::sendResetLink(['email' => Str::lower(trim($data['email']))]);

        return back()->with('status', 'Si existe una cuenta con ese correo, recibirás las instrucciones para recuperar el acceso.');
    }

    public function resetForm(Request $request, string $token)
    {
        return Inertia::render('auth/ResetPassword', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => PasswordRequirements::rules(),
        ]);
        $data['email'] = Str::lower(trim($data['email']));
        $status = Password::reset($data, function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                Audit::record('auth.password_reset', $user->id, $user->id);
            });
            event(new PasswordReset($user));
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o ha vencido. Solicita uno nuevo.']);
        }

        return redirect()->route('login')->with('status', 'Contraseña actualizada. Inicia sesión de nuevo.');
    }

    public function verify(EmailVerificationRequest $request)
    {
        DB::transaction(fn () => $request->fulfill());

        return redirect()->route('account')->with('status', 'Correo verificado.');
    }

    public function resend(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Se ha solicitado un nuevo enlace de verificación.');
    }
}
