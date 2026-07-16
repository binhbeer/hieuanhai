<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('settings')->where('key', 'ai.image_max_reference_photos')->value('value');

        if ($setting !== null && (int) json_decode((string) $setting, true) === 3) {
            DB::table('settings')
                ->where('key', 'ai.image_max_reference_photos')
                ->update(['value' => json_encode(5), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $setting = DB::table('settings')->where('key', 'ai.image_max_reference_photos')->value('value');

        if ($setting !== null && (int) json_decode((string) $setting, true) === 5) {
            DB::table('settings')
                ->where('key', 'ai.image_max_reference_photos')
                ->update(['value' => json_encode(3), 'updated_at' => now()]);
        }
    }
};
