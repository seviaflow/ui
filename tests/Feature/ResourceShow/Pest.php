<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

require __DIR__.'/../Support/ResourceShowSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});
