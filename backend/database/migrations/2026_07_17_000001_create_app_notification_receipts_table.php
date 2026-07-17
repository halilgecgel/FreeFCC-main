<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notification_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_notification_id')
                ->constrained('app_notifications')
                ->cascadeOnDelete();
            $table->foreignId('member_id')
                ->constrained('members')
                ->cascadeOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['app_notification_id', 'member_id'], 'notif_receipt_unique');
            $table->index(['member_id', 'delivered_at']);
            $table->index(['member_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notification_receipts');
    }
};
