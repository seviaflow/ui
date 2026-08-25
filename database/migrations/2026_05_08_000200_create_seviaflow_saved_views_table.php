<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seviaflow_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->string('resource');
            $table->string('name');
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->string('visibility')->default('private');
            $table->json('state');
            $table->timestamps();

            $table->index(['resource', 'visibility'], 'seviaflow_saved_views_resource_visibility_index');
            $table->index(['user_type', 'user_id'], 'seviaflow_saved_views_user_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seviaflow_saved_views');
    }
};
