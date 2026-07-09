<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $userIds = DB::table('ai_api_keys')
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $keys = DB::table('ai_api_keys')
                    ->where('user_id', $userId)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->get(['id', 'quota_used']);

                $keep = $keys->first();

                if (! $keep) {
                    continue;
                }

                $dropIds = $keys->pluck('id')->skip(1)->values();

                DB::table('ai_api_requests')
                    ->whereIn('ai_api_key_id', $dropIds)
                    ->update(['ai_api_key_id' => $keep->id]);

                DB::table('ai_api_keys')
                    ->where('id', $keep->id)
                    ->update(['quota_used' => (int) $keys->sum('quota_used')]);

                DB::table('ai_api_keys')->whereIn('id', $dropIds)->delete();
            }
        });

        Schema::table('ai_api_keys', function (Blueprint $table): void {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_api_keys', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
        });
    }
};
