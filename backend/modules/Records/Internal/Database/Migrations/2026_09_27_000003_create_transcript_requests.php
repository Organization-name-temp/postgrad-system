<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('user_id');
            $table->string('delivery_method');
            $table->unsignedInteger('fee_minor');
            $table->text('instructions_en');
            $table->text('instructions_ar');
            $table->string('status');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        DB::statement("CREATE UNIQUE INDEX transcript_one_open_request ON transcript_requests (student_id) WHERE status NOT IN ('Completed', 'Rejected', 'Cancelled')");
        DB::statement("ALTER TABLE transcript_requests ADD CONSTRAINT transcript_delivery_method CHECK (delivery_method IN ('digital', 'collection'))");
        Schema::create('transcript_request_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transcript_request_id')->constrained('transcript_requests')->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('actor_user_id');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_request_transitions');
        Schema::dropIfExists('transcript_requests');
    }
};
