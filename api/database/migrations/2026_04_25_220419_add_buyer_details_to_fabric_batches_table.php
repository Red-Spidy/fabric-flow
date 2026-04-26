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
        Schema::table('fabric_batches', function (Blueprint $table) {
            $table->string('buyer_email')->nullable()->after('supplier_contact');
            $table->string('buyer_name')->nullable()->after('supplier_contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fabric_batches', function (Blueprint $table) {
            $table->dropColumn(['buyer_email', 'buyer_name']);
        });
    }
};
