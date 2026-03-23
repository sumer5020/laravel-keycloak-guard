<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organizations table for Keycloak 26 Organizations feature.
 * Only required when keycloak.organizations.sync_to_database = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('keycloak_org_id')->unique()->index()
                ->comment('The Keycloak organization UUID from the JWT organization claim');
            $table->string('alias')->unique()
                ->comment('The org alias used as key in the JWT (e.g. acme-corp)');
            $table->string('name');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('roles')->default('[]')
                ->comment('Organization-level roles as defined in Keycloak');
            $table->timestamp('updated_at')->nullable();

            $table->primary(['organization_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
