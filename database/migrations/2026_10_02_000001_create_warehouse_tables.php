<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 80)->nullable()->unique();
        });
        Schema::create('warehouse_mutexes', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
        });
        DB::table('warehouse_mutexes')->insert(['id' => 1]);
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('rooms', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->string('code', 30)->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->string('code', 10);
            $t->string('name', 100);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['room_id', 'code']);
        });
        Schema::create('racks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('block_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('rack_number');
            $t->string('name', 100)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['block_id', 'rack_number']);
        });
        Schema::create('rack_positions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rack_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('position_number');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['rack_id', 'position_number']);
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('product_code', 60)->unique();
            $t->string('name', 150)->index();
            $t->string('specification', 150)->nullable();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('unit', 20);
            $t->unsignedInteger('stock')->default(0);
            $t->foreignId('rack_position_id')->constrained()->restrictOnDelete();
            $t->uuid('qr_token')->unique();
            $t->enum('moving_type', ['FAST', 'SLOW'])->nullable()->index();
            $t->string('image')->nullable();
            $t->text('description')->nullable();
            $t->boolean('active')->default(true)->index();
            // NULL values may repeat; an active position may occur only once, even outside Laravel.
            $t->unsignedBigInteger('active_position_id')->nullable()->storedAs('CASE WHEN active = 1 THEN rack_position_id ELSE NULL END')->unique();
            $t->timestamps();
        });
        Schema::create('transaction_sequences', function (Blueprint $t) {
            $t->date('sequence_date');
            $t->string('type', 3);
            $t->unsignedBigInteger('last_number')->default(0);
            $t->primary(['sequence_date', 'type']);
        });
        Schema::create('stock_transactions', function (Blueprint $t) {
            $t->id();
            $t->string('transaction_number', 40)->unique();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('rack_position_id')->constrained()->restrictOnDelete();
            $t->string('location_snapshot', 100);
            $t->string('room_snapshot', 100);
            $t->string('product_code_snapshot', 60);
            $t->string('name_snapshot', 150);
            $t->string('specification_snapshot', 150)->nullable();
            $t->string('unit_snapshot', 20);
            $t->enum('transaction_type', ['IN', 'OUT', 'ADJUSTMENT'])->index();
            $t->integer('quantity')->nullable();
            $t->unsignedInteger('stock_before')->nullable();
            $t->unsignedInteger('stock_after')->nullable();
            $t->enum('status', ['PENDING', 'APPROVED', 'CANCELLED'])->default('PENDING')->index();
            $t->unsignedBigInteger('pending_product_id')->nullable()->storedAs("CASE WHEN status = 'PENDING' THEN product_id ELSE NULL END")->unique();
            $t->dateTime('scanned_at')->nullable()->index();
            $t->dateTime('approved_at')->nullable()->index();
            $t->dateTime('cancelled_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->uuid('request_key')->nullable()->unique();
            $t->timestamps();
            $t->index(['product_id', 'transaction_type', 'status']);
        });
        // Both MySQL >=8.0.16 and MariaDB >=10.6 enforce these checks.
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_stock_range CHECK (stock BETWEEN 0 AND 2147483647)');
        DB::statement('ALTER TABLE racks ADD CONSTRAINT chk_rack_number CHECK (rack_number BETWEEN 1 AND 999)');
        DB::statement('ALTER TABLE rack_positions ADD CONSTRAINT chk_position_number CHECK (position_number BETWEEN 1 AND 999)');
        DB::statement("ALTER TABLE stock_transactions ADD CONSTRAINT chk_approved_values CHECK (status <> 'APPROVED' OR (quantity IS NOT NULL AND stock_before IS NOT NULL AND stock_after IS NOT NULL AND approved_at IS NOT NULL AND stock_after BETWEEN 0 AND 2147483647 AND ((transaction_type = 'IN' AND quantity > 0 AND stock_after = stock_before + quantity) OR (transaction_type = 'OUT' AND quantity > 0 AND stock_before = stock_after + quantity) OR (transaction_type = 'ADJUSTMENT' AND stock_after = stock_before + quantity))))");
    }

    public function down(): void
    {
        foreach (['stock_transactions', 'transaction_sequences', 'products', 'rack_positions', 'racks', 'blocks', 'rooms', 'categories', 'warehouse_mutexes'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('username');
        });
    }
};
