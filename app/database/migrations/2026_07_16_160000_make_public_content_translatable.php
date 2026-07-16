<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->toJson('categories', [
            'name' => fn (Blueprint $table) => $table->json('name_i18n')->nullable(),
            'description' => fn (Blueprint $table) => $table->json('description_i18n')->nullable(),
        ]);
        Schema::table('categories', fn (Blueprint $table) => $table->string('slug_en')->nullable()->unique()->after('slug'));

        $this->toJson('tags', [
            'name' => fn (Blueprint $table) => $table->json('name_i18n')->nullable(),
            'description' => fn (Blueprint $table) => $table->json('description_i18n')->nullable(),
        ]);
        Schema::table('tags', fn (Blueprint $table) => $table->string('slug_en')->nullable()->unique()->after('slug'));

        $this->toJson('generated_media', [
            'title' => fn (Blueprint $table) => $table->json('title_i18n')->nullable(),
            'description' => fn (Blueprint $table) => $table->json('description_i18n')->nullable(),
        ]);
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('slug_en'));
        $this->toScalar('categories', [
            'name' => fn (Blueprint $table) => $table->string('name_scalar')->nullable(),
            'description' => fn (Blueprint $table) => $table->string('description_scalar')->nullable(),
        ]);

        Schema::table('tags', fn (Blueprint $table) => $table->dropColumn('slug_en'));
        $this->toScalar('tags', [
            'name' => fn (Blueprint $table) => $table->string('name_scalar')->nullable(),
            'description' => fn (Blueprint $table) => $table->string('description_scalar')->nullable(),
        ]);

        $this->toScalar('generated_media', [
            'title' => fn (Blueprint $table) => $table->string('title_scalar')->nullable(),
            'description' => fn (Blueprint $table) => $table->text('description_scalar')->nullable(),
        ]);
    }

    /**
     * @param  array<string, Closure(Blueprint): mixed>  $columns
     */
    private function toJson(string $tableName, array $columns): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($columns): void {
            foreach ($columns as $addColumn) {
                $addColumn($table);
            }
        });

        DB::table($tableName)->orderBy('id')->chunkById(500, function ($rows) use ($columns, $tableName): void {
            foreach ($rows as $row) {
                $values = [];

                foreach (array_keys($columns) as $column) {
                    $value = $row->{$column};
                    $values[$column.'_i18n'] = $value === null ? null : json_encode(['vi' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                DB::table($tableName)->where('id', $row->id)->update($values);
            }
        });

        Schema::table($tableName, function (Blueprint $table) use ($columns): void {
            $table->dropColumn(array_keys($columns));

            foreach (array_keys($columns) as $column) {
                $table->renameColumn($column.'_i18n', $column);
            }
        });
    }

    /**
     * @param  array<string, Closure(Blueprint): mixed>  $columns
     */
    private function toScalar(string $tableName, array $columns): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($columns): void {
            foreach ($columns as $addColumn) {
                $addColumn($table);
            }
        });

        DB::table($tableName)->orderBy('id')->chunkById(500, function ($rows) use ($columns, $tableName): void {
            foreach ($rows as $row) {
                $values = [];

                foreach (array_keys($columns) as $column) {
                    $translations = json_decode((string) $row->{$column}, true);
                    $values[$column.'_scalar'] = is_array($translations) ? ($translations['vi'] ?? null) : null;
                }

                DB::table($tableName)->where('id', $row->id)->update($values);
            }
        });

        Schema::table($tableName, function (Blueprint $table) use ($columns): void {
            $table->dropColumn(array_keys($columns));

            foreach (array_keys($columns) as $column) {
                $table->renameColumn($column.'_scalar', $column);
            }
        });
    }
};
