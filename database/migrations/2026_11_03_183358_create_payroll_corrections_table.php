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
            $table->bigInteger('amount_cents');
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_corrections');
    }
};
