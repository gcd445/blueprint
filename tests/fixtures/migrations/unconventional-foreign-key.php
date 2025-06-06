<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('countries_id')->constrained('countries');
            $table->string('country_code');
            $table->foreign('country_code')->references('code')->on('countries');
            $table->string('ccid');
            $table->foreign('ccid')->references('id')->on('country');
            $table->string('c_code');
            $table->foreign('c_code')->references('code')->on('countries');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
