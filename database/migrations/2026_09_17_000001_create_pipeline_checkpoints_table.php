<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable record of how far a project's AI pipeline got.
 *
 * Before this, a provider failing halfway lost everything: the run threw, and
 * the next attempt started again at the business analysis — paying for Gemini
 * and the designer a second time to arrive back at the same point. A completed
 * stage now keeps its result here, so a retry resumes at the stage that failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->string('status')->default('completed'); // completed | failed
            // The stage's result, replayed instead of recomputed on a retry.
            $table->longText('payload')->nullable();
            // ProviderException::CODES. Never a key or a raw provider payload —
            // see ProviderException::sanitise().
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_checkpoints');
    }
};
