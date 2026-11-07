<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;

it('renders auth pages with the selected locale using cached Blade views', function (string $locale, string $login, string $email, string $reset): void {
    app()->setLocale($locale);
    pest_test_case()->get('/admin/login')->assertOk()->assertSee($login)->assertSee($email)
        ->assertSee('name="password"', false)->assertSee('autocomplete="current-password"', false);
    pest_test_case()->get('/admin/forgot-password')->assertOk()->assertSee($reset);
    pest_test_case()->get('/admin/reset-password/test-token')->assertOk()->assertSee($email);

    app()->setLocale($locale === 'pl' ? 'en' : 'pl');
    pest_test_case()->get('/admin/login')->assertOk()
        ->assertSee($locale === 'pl' ? 'Sign in to your account' : 'Zaloguj się do swojego konta');
})->with([
    'English' => ['en', 'Sign in to your account', 'Email address', 'Send reset link'],
    'Polish' => ['pl', 'Zaloguj się do swojego konta', 'Adres e-mail', 'Wyślij odnośnik do resetowania'],
]);

it('localizes broker success and failure messages at their source', function (string $locale, string $sent, string $missing): void {
    app()->setLocale($locale);
    $broker = Mockery::mock();
    $broker->shouldReceive('sendResetLink')->once()->andReturn(Password::RESET_LINK_SENT);
    $broker->shouldReceive('sendResetLink')->once()->andReturn(Password::INVALID_USER);
    Password::shouldReceive('broker')->twice()->with('users')->andReturn($broker);

    pest_test_case()->post('/admin/forgot-password', ['email' => 'user@example.com'])
        ->assertSessionHas('status', $sent);
    pest_test_case()->post('/admin/forgot-password', ['email' => 'missing@example.com'])
        ->assertSessionHasErrors(['email' => $missing]);
})->with([
    'English' => ['en', 'We have emailed your password reset link.', "We can't find a user with that email address."],
    'Polish' => ['pl', 'Wysłano odnośnik do resetowania hasła.', 'Nie znaleziono konta z tym adresem e-mail.'],
]);

it('localizes invalid credentials without exposing or retaining passwords', function (string $locale, string $message): void {
    app()->setLocale($locale);
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email');
        $table->string('password');
    });

    pest_test_case()->from('/admin/login')->post('/admin/login', [
        'email' => 'missing@example.com',
        'password' => 'secret-password',
    ])->assertRedirect('/admin/login')->assertSessionHasErrors(['email' => $message]);

    expect(session()->getOldInput('email'))->toBe('missing@example.com')
        ->and(session()->getOldInput('password'))->toBeNull();
})->with([
    'English' => ['en', 'These credentials do not match our records.'],
    'Polish' => ['pl', 'Nieprawidłowy adres e-mail lub hasło.'],
]);
