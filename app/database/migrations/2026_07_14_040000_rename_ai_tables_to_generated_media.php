<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const TABLES = [
        'ai_images' => 'generated_media',
        'ai_image_favorites' => 'media_favorites',
        'ai_image_tag' => 'media_tag',
        'ai_tags' => 'tags',
        'ai_api_keys' => 'api_keys',
        'ai_api_requests' => 'api_requests',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const COLUMNS = [
        'media_favorites' => [
            'ai_image_id' => 'media_id',
        ],
        'media_tag' => [
            'ai_image_id' => 'media_id',
            'ai_tag_id' => 'tag_id',
        ],
        'api_requests' => [
            'ai_api_key_id' => 'api_key_id',
            'ai_image_id' => 'media_id',
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const INDEXES = [
        'generated_media' => [
            'ai_images_visitor_key_created_at_index' => 'generated_media_visitor_key_created_at_index',
            'ai_images_visitor_key_index' => 'generated_media_visitor_key_index',
            'ai_images_status_index' => 'generated_media_status_index',
            'ai_images_is_published_published_at_index' => 'generated_media_is_published_published_at_index',
            'ai_images_category_id_is_published_published_at_index' => 'generated_media_category_id_is_published_published_at_index',
            'ai_images_is_featured_published_at_index' => 'generated_media_is_featured_published_at_index',
            'ai_images_source_index' => 'generated_media_source_index',
        ],
        'media_favorites' => [
            'ai_image_favorites_user_id_ai_image_id_unique' => 'media_favorites_user_id_media_id_unique',
            'ai_image_favorites_ai_image_id_created_at_index' => 'media_favorites_media_id_created_at_index',
        ],
        'media_tag' => [
            'ai_image_tag_ai_tag_id_ai_image_id_index' => 'media_tag_tag_id_media_id_index',
        ],
        'tags' => [
            'ai_tags_slug_unique' => 'tags_slug_unique',
        ],
        'api_keys' => [
            'ai_api_keys_token_hash_unique' => 'api_keys_token_hash_unique',
            'ai_api_keys_user_id_unique' => 'api_keys_user_id_unique',
            'ai_api_keys_user_id_index' => 'api_keys_user_id_index',
        ],
        'api_requests' => [
            'ai_api_requests_ai_api_key_id_created_at_index' => 'api_requests_api_key_id_created_at_index',
            'ai_api_requests_status_index' => 'api_requests_status_index',
        ],
    ];

    /**
     * MySQL creates these supporting indexes implicitly for foreign keys.
     *
     * @var array<string, array<string, string>>
     */
    private const MYSQL_FOREIGN_INDEXES = [
        'generated_media' => [
            'ai_images_user_id_foreign' => 'generated_media_user_id_foreign',
            'ai_images_parent_id_foreign' => 'generated_media_parent_id_foreign',
        ],
        'api_requests' => [
            'ai_api_requests_user_id_foreign' => 'api_requests_user_id_foreign',
            'ai_api_requests_ai_image_id_foreign' => 'api_requests_media_id_foreign',
        ],
    ];

    public function up(): void
    {
        $this->dropForeignKeys(false);
        $this->renameTables(false);
        $this->renameColumns(false);
        $this->renameIndexes(false);
        $this->addForeignKeys(true);
    }

    public function down(): void
    {
        $this->dropForeignKeys(true);
        $this->renameIndexes(true);
        $this->renameColumns(true);
        $this->renameTables(true);
        $this->addForeignKeys(false);
    }

    private function dropForeignKeys(bool $renamed): void
    {
        foreach ($this->foreignKeys($renamed) as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
                foreach (array_keys($foreignKeys) as $column) {
                    $table->dropForeign([$column]);
                }
            });
        }
    }

    private function addForeignKeys(bool $renamed): void
    {
        foreach ($this->foreignKeys($renamed) as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
                foreach ($foreignKeys as $column => [$target, $deleteAction]) {
                    $table->foreign($column)->references('id')->on($target)->onDelete($deleteAction);
                }
            });
        }
    }

    private function renameTables(bool $reverse): void
    {
        foreach (self::TABLES as $old => $new) {
            Schema::rename($reverse ? $new : $old, $reverse ? $old : $new);
        }
    }

    private function renameColumns(bool $reverse): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $reverse): void {
                foreach ($columns as $old => $new) {
                    $table->renameColumn($reverse ? $new : $old, $reverse ? $old : $new);
                }
            });
        }
    }

    private function renameIndexes(bool $reverse): void
    {
        $indexes = self::INDEXES;

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            foreach (self::MYSQL_FOREIGN_INDEXES as $tableName => $foreignIndexes) {
                $indexes[$tableName] = [...$indexes[$tableName], ...$foreignIndexes];
            }
        }

        foreach ($indexes as $tableName => $tableIndexes) {
            Schema::table($tableName, function (Blueprint $table) use ($tableIndexes, $reverse): void {
                foreach ($tableIndexes as $old => $new) {
                    $table->renameIndex($reverse ? $new : $old, $reverse ? $old : $new);
                }
            });
        }
    }

    /**
     * @return array<string, array<string, array{string, string}>>
     */
    private function foreignKeys(bool $renamed): array
    {
        if ($renamed) {
            return [
                'generated_media' => [
                    'user_id' => ['users', 'set null'],
                    'parent_id' => ['generated_media', 'set null'],
                    'category_id' => ['categories', 'set null'],
                ],
                'media_favorites' => [
                    'user_id' => ['users', 'cascade'],
                    'media_id' => ['generated_media', 'cascade'],
                ],
                'media_tag' => [
                    'media_id' => ['generated_media', 'cascade'],
                    'tag_id' => ['tags', 'cascade'],
                ],
                'api_keys' => [
                    'user_id' => ['users', 'cascade'],
                ],
                'api_requests' => [
                    'api_key_id' => ['api_keys', 'cascade'],
                    'user_id' => ['users', 'set null'],
                    'media_id' => ['generated_media', 'set null'],
                ],
            ];
        }

        return [
            'ai_images' => [
                'user_id' => ['users', 'set null'],
                'parent_id' => ['ai_images', 'set null'],
                'category_id' => ['categories', 'set null'],
            ],
            'ai_image_favorites' => [
                'user_id' => ['users', 'cascade'],
                'ai_image_id' => ['ai_images', 'cascade'],
            ],
            'ai_image_tag' => [
                'ai_image_id' => ['ai_images', 'cascade'],
                'ai_tag_id' => ['ai_tags', 'cascade'],
            ],
            'ai_api_keys' => [
                'user_id' => ['users', 'cascade'],
            ],
            'ai_api_requests' => [
                'ai_api_key_id' => ['ai_api_keys', 'cascade'],
                'user_id' => ['users', 'set null'],
                'ai_image_id' => ['ai_images', 'set null'],
            ],
        ];
    }
};
