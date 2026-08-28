<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 背诵范围按（用户，背诵源）独立：为排除表增加 source 维度。
     * SQLite 无法修改复合主键，故重建表；历史行统一归入 selected 源。
     */
    public function up(): void
    {
        Schema::create('scope_exclusions_new', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'source', 'card_id']);
        });

        DB::table('scope_exclusions')->orderBy('user_id')->chunk(500, function ($rows) {
            DB::table('scope_exclusions_new')->insert(
                $rows->map(fn ($row) => [
                    'user_id' => $row->user_id,
                    'source' => 'selected',
                    'card_id' => $row->card_id,
                ])->all(),
            );
        });

        Schema::drop('scope_exclusions');
        Schema::rename('scope_exclusions_new', 'scope_exclusions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('scope_exclusions_old', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'card_id']);
        });

        DB::table('scope_exclusions')->orderBy('user_id')->chunk(500, function ($rows) {
            DB::table('scope_exclusions_old')->insertOrIgnore(
                $rows->map(fn ($row) => [
                    'user_id' => $row->user_id,
                    'card_id' => $row->card_id,
                ])->all(),
            );
        });

        Schema::drop('scope_exclusions');
        Schema::rename('scope_exclusions_old', 'scope_exclusions');
    }
};
