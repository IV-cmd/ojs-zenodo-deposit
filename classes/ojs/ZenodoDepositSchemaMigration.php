<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes\ojs;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class ZenodoDepositSchemaMigration extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zenodo_deposits')) {
            return;
        }

        Schema::create('zenodo_deposits', static function (Blueprint $table): void {
            $table->unsignedBigInteger('publication_id')->primary();
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('context_id');
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('deposition_id')->nullable();
            $table->unsignedBigInteger('dataset_deposition_id')->nullable();
            $table->string('doi', 255)->nullable();
            $table->string('dataset_doi', 255)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zenodo_deposits');
    }
}
