<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

final class TwoFactorAuthenticationManager
{
    private const ENABLE_ACTION = 'Laravel\\Fortify\\Actions\\EnableTwoFactorAuthentication';

    private const DISABLE_ACTION = 'Laravel\\Fortify\\Actions\\DisableTwoFactorAuthentication';

    private const REGENERATE_ACTION = 'Laravel\\Fortify\\Actions\\GenerateNewRecoveryCodes';

    public function available(): bool
    {
        return class_exists(self::ENABLE_ACTION)
            && class_exists(self::DISABLE_ACTION)
            && class_exists(self::REGENERATE_ACTION);
    }

    public function enabled(Model $user): bool
    {
        if (! method_exists($user, 'hasEnabledTwoFactorAuthentication')) {
            return false;
        }

        return (bool) $user->hasEnabledTwoFactorAuthentication();
    }

    public function qrCodeSvg(Model $user): ?string
    {
        if (! method_exists($user, 'twoFactorQrCodeSvg')) {
            return null;
        }

        $value = $user->twoFactorQrCodeSvg();

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

        $codes = $user->recoveryCodes();

        return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
    }

    public function enable(Model $user): bool
    {
        if (! $this->available()) {
            return false;
        }

        $action = App::make(self::ENABLE_ACTION);
        $action($user);

        return true;
    }

    public function disable(Model $user): bool
    {
        if (! $this->available()) {
            return false;
        }

        $action = App::make(self::DISABLE_ACTION);
        $action($user);

        return true;
    }

    public function regenerateRecoveryCodes(Model $user): bool
    {
        if (! $this->available()) {
            return false;
        }

        $action = App::make(self::REGENERATE_ACTION);
        $action($user);

        return true;
    }
}
