<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Laravel\Fortify\Fortify;
use Throwable;

final class TwoFactorAuthenticationManager
{
    private const ENABLE_ACTION = 'Laravel\\Fortify\\Actions\\EnableTwoFactorAuthentication';

    private const DISABLE_ACTION = 'Laravel\\Fortify\\Actions\\DisableTwoFactorAuthentication';

    private const REGENERATE_ACTION = 'Laravel\\Fortify\\Actions\\GenerateNewRecoveryCodes';

    private const CONFIRM_ACTION = 'Laravel\\Fortify\\Actions\\ConfirmTwoFactorAuthentication';

    public function available(?Model $user = null): bool
    {
        return class_exists(self::ENABLE_ACTION)
            && class_exists(self::DISABLE_ACTION)
            && class_exists(self::REGENERATE_ACTION)
            && $this->fortifyProvider() !== null
            && ($user === null || (
                method_exists($user, 'hasEnabledTwoFactorAuthentication')
                && method_exists($user, 'twoFactorQrCodeSvg')
                && method_exists($user, 'recoveryCodes')
                && method_exists($user, 'replaceRecoveryCode')
            ));
    }

    public function setupPending(Model $user): bool
    {
        return filled($user->getAttribute('two_factor_secret')) && ! $this->enabled($user);
    }

    public function confirm(Model $user, string $code): bool
    {
        if (! $this->available($user) || ! $this->setupPending($user) || ! class_exists(self::CONFIRM_ACTION)) {
            return false;
        }

        try {
            $action = App::make(self::CONFIRM_ACTION);
            $action($user, trim($code));

            return $this->enabled($user);
        } catch (Throwable) {
            return false;
        }
    }

    public function enabled(Model $user): bool
    {
        if (! method_exists($user, 'hasEnabledTwoFactorAuthentication')) {
            return false;
        }

        return (bool) $user->hasEnabledTwoFactorAuthentication();
    }

    public function challengeRequired(Model $user): bool
    {
        return (bool) config('seviaflow-ui.auth.two_factor_authentication', true)
            && (bool) config('seviaflow-ui.auth.two_factor_login.enabled', true)
            && $this->enabled($user);
    }

    public function loginChallengeAvailable(Model $user): bool
    {
        if (is_callable(config('seviaflow-ui.auth.two_factor_login.verify'))) {
            return true;
        }

        if (is_callable(config('seviaflow-ui.auth.two_factor_login.consume_recovery_code'))) {
            return true;
        }

        if (method_exists($user, 'consumeTwoFactorRecoveryCode')) {
            return true;
        }

        if (method_exists($user, 'replaceRecoveryCode') && method_exists($user, 'recoveryCodes')) {
            return true;
        }

        return $this->fortifyProvider() !== null && $this->twoFactorSecret($user) !== null;
    }

    public function verifyLoginChallenge(Model $user, string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        $verifier = config('seviaflow-ui.auth.two_factor_login.verify');
        if (is_callable($verifier)) {
            try {
                if ((bool) call_user_func($verifier, $user, $code)) {
                    return true;
                }
            } catch (Throwable) {
                return false;
            }
        }

        if ($this->consumeRecoveryCode($user, $code)) {
            return true;
        }

        if (is_callable($verifier)) {
            return false;
        }

        $provider = $this->fortifyProvider();
        $secret = $this->twoFactorSecret($user);
        if ($provider === null || $secret === null || ! method_exists($provider, 'verify')) {
            return false;
        }

        try {
            return (bool) $provider->verify($secret, $code);
        } catch (Throwable) {
            return false;
        }
    }

    public function qrCodeSvg(Model $user): ?string
    {
        if (! method_exists($user, 'twoFactorQrCodeSvg')) {
            return null;
        }

        try {
            $value = $user->twoFactorQrCodeSvg();
        } catch (Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    public function recoveryCodes(Model $user): array
    {
        if (! method_exists($user, 'recoveryCodes')) {
            return [];
        }

        try {
            $codes = $user->recoveryCodes();
        } catch (Throwable) {
            return [];
        }

        return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
    }

    public function enable(Model $user): bool
    {
        if (! $this->available($user)) {
            return false;
        }

        $action = App::make(self::ENABLE_ACTION);
        $action($user);

        return true;
    }

    public function disable(Model $user): bool
    {
        if (! $this->available($user)) {
            return false;
        }

        $action = App::make(self::DISABLE_ACTION);
        $action($user);

        return true;
    }

    public function regenerateRecoveryCodes(Model $user): bool
    {
        if (! $this->available($user) || ! $this->enabled($user)) {
            return false;
        }

        $action = App::make(self::REGENERATE_ACTION);
        $action($user);

        return true;
    }

    private function consumeRecoveryCode(Model $user, string $code): bool
    {
        $consumer = config('seviaflow-ui.auth.two_factor_login.consume_recovery_code');
        if (is_callable($consumer)) {
            try {
                return (bool) call_user_func($consumer, $user, $code);
            } catch (Throwable) {
                return false;
            }
        }

        if (method_exists($user, 'consumeTwoFactorRecoveryCode')) {
            try {
                return (bool) $user->consumeTwoFactorRecoveryCode($code);
            } catch (Throwable) {
                return false;
            }
        }

        if (! method_exists($user, 'replaceRecoveryCode')) {
            return false;
        }

        if (! in_array($code, $this->recoveryCodes($user), true)) {
            return false;
        }

        try {
            if ($user->exists) {
                return $user->getConnection()->transaction(function () use ($user, $code): bool {
                    $locked = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->first();
                    if (! $locked instanceof Model || ! method_exists($locked, 'replaceRecoveryCode')
                        || ! in_array($code, $this->recoveryCodes($locked), true)) {
                        return false;
                    }

                    $locked->replaceRecoveryCode($code);

                    return true;
                });
            }

            $user->replaceRecoveryCode($code);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function fortifyProvider(): ?object
    {
        $contract = 'Laravel\\Fortify\\Contracts\\TwoFactorAuthenticationProvider';
        if (! interface_exists($contract) || ! App::bound($contract)) {
            return null;
        }

        try {
            $provider = App::make($contract);

            return is_object($provider) ? $provider : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function twoFactorSecret(Model $user): ?string
    {
        foreach (['twoFactorSecret', 'getTwoFactorSecret'] as $method) {
            if (! method_exists($user, $method)) {
                continue;
            }

            try {
                $secret = $user->{$method}();
                if (is_string($secret) && $secret !== '') {
                    return $secret;
                }
            } catch (Throwable) {
                return null;
            }
        }

        $secret = $user->getAttribute('two_factor_secret');

        if (! is_string($secret) || $secret === '' || ! class_exists(Fortify::class)) {
            return null;
        }

        try {
            $decrypted = Fortify::currentEncrypter()->decrypt($secret);

            return is_string($decrypted) && $decrypted !== '' ? $decrypted : null;
        } catch (Throwable) {
            return null;
        }
    }
}
