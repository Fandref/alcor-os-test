<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_line_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->bigInteger('initial_amount_cents');
            $table->bigInteger('amount_cents');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_corrections');
    }
};
