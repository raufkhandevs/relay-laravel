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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            // Generated object key. Owes nothing to the client-supplied filename:
            // the filename is attacker-controlled and never touches this column.
            $table->string('disk_path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
