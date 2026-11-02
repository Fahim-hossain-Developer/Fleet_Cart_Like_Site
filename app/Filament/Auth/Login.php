<?php

namespace App\Filament\Auth;

use App\Domain\Customers\Actions\AdminCode;
use App\Models\Admin;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Auth\SessionGuard;

class Login extends \Filament\Pages\Auth\Login
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $e) {
            $this->getRateLimitedNotification($e)?->send();

            return null;
        }
        $data = $this->form->getState();
        $credentials = $this->getCredentialsFromFormData($data);
        /** @var SessionGuard $guard */
        $guard = auth('admin');
        $provider = $guard->getProvider();
        $a = $provider->retrieveByCredentials($credentials);
        if (! $a instanceof Admin || ! $a->is_active || ! $provider->validateCredentials($a, $credentials) || ! $a->canAccessPanel(Filament::getCurrentPanel())) {
            $this->throwFailureValidationException();
        }
        $this->data['password'] = null;
        if (app(AdminCode::class)->required($a)) {
            $guard->logout();
            session()->regenerate();
            app(AdminCode::class)->begin($a, (bool) ($data['remember'] ?? false));
            $this->redirect(route('admin.otp'));

            return null;
        }
        $guard->login($a, (bool) ($data['remember'] ?? false));
        session()->regenerate();

        return app(LoginResponse::class);
    }
}
