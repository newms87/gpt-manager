<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update old OCR name to new constant value
        DB::table('stored_files')
            ->where('transcode_name', 'OCR')
            ->update(['transcode_name' => 'Image To Text OCR']);

        // Update old LLM transcoder name to new constant value
        DB::table('stored_files')
            ->where('transcode_name', 'Image To Text Transcoder')
            ->update(['transcode_name' => 'Image To Text LLM']);
    }

    public function down(): void
    {
        // Revert to old names
        DB::table('stored_files')
            ->where('transcode_name', 'Image To Text OCR')
            ->update(['transcode_name' => 'OCR']);

        DB::table('stored_files')
            ->where('transcode_name', 'Image To Text LLM')
            ->update(['transcode_name' => 'Image To Text Transcoder']);
    }
};
