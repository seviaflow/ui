<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Ui\Auth\TwoFactorAuthenticationManager;

it('uses the configured login verifier for an enabled account', function (): void {
    $user = new TwoFactorManagerUser;
    $manager = new TwoFactorAuthenticationManager;
    $verifiedCode = null;

    config()->set('seviaflow-ui.auth.two_factor_login.verify', static function (Model $user, string $code) use (&$verifiedCode): bool {
        $verifiedCode = $code;

        return $user instanceof TwoFactorManagerUser && $code === '123456';
    });

    expect($manager->challengeRequired($user))->toBeTrue()
        ->and($manager->loginChallengeAvailable($user))->toBeTrue()
        ->and($manager->verifyLoginChallenge($user, ' 123456 '))->toBeTrue()
        ->and($verifiedCode)->toBe('123456')
        ->and($manager->verifyLoginChallenge($user, '654321'))->toBeFalse();
});

it('consumes a recovery code only once', function (): void {
    $user = new TwoFactorManagerUser;
    $manager = new TwoFactorAuthenticationManager;

    config()->set('seviaflow-ui.auth.two_factor_login.verify', null);

    expect($manager->loginChallengeAvailable($user))->toBeTrue()
        ->and($manager->verifyLoginChallenge($user, 'recovery-code'))->toBeTrue()
        ->and($manager->verifyLoginChallenge($user, 'recovery-code'))->toBeFalse();
});

it('allows recovery codes when a configured OTP verifier rejects them', function (): void {
    $user = new TwoFactorManagerUser;
    $manager = new TwoFactorAuthenticationManager;
    config()->set('seviaflow-ui.auth.two_factor_login.verify', static fn (): bool => false);

    expect($manager->verifyLoginChallenge($user, 'recovery-code'))->toBeTrue()
        ->and($manager->verifyLoginChallenge($user, 'recovery-code'))->toBeFalse();
});

it('fails closed when an enabled account has no challenge adapter', function (): void {
    $user = new TwoFactorNoAdapterUser;
    $manager = new TwoFactorAuthenticationManager;

    config()->set('seviaflow-ui.auth.two_factor_login.verify', null);

    expect($manager->challengeRequired($user))->toBeTrue()
        ->and($manager->loginChallengeAvailable($user))->toBeFalse()
        ->and($manager->verifyLoginChallenge($user, '123456'))->toBeFalse();
});

final class TwoFactorManagerUser extends Model
{
    protected $table = 'users';

    public bool $recoveryCodeConsumed = false;

    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    public function recoveryCodes(): array
    {
        return $this->recoveryCodeConsumed ? [] : ['recovery-code'];
    }

    public function replaceRecoveryCode(string $code): void
    {
        $this->recoveryCodeConsumed = true;
    }
}

final class TwoFactorNoAdapterUser extends Model
{
    protected $table = 'users';

    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return true;
    }
}
