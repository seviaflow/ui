<?php

declare(strict_types=1);

use SeviaFlow\Ui\Index\Import\ImportModalState;

it('returns reset state when opening and closing import modal', function (): void {
    $state = new ImportModalState;

    expect($state->open())->toMatchArray([
        'show_import_modal' => true,
        'import_file' => null,
        'import_preview_headers' => [],
        'import_preview_rows' => [],
        'import_preview_errors' => [],
    ])->and($state->close())->toMatchArray([
        'show_import_modal' => false,
        'import_file' => null,
        'import_preview_headers' => [],
        'import_preview_rows' => [],
        'import_preview_errors' => [],
    ]);
});
