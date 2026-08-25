<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seviaflow_dashboard_layouts', function (Blueprint $table): void {
            $table->id();
            $table->string('dashboard');
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->json('layout');
            $table->timestamps();

            $table->unique(['dashboard', 'user_type', 'user_id'], 'seviaflow_dashboard_layouts_scope_unique');
            $table->index(['user_type', 'user_id'], 'seviaflow_dashboard_layouts_user_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seviaflow_dashboard_layouts');
    }
};
