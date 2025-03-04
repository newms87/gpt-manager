<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = Schema::getTables();

        foreach($tables as $tableData) {
            $tableName = $tableData['name'];
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'created_at')) {
                    $table->timestamp('created_at', 3)->nullable()->change();
                }

                if (Schema::hasColumn($tableName, 'updated_at')) {
                    $table->timestamp('updated_at', 3)->nullable()->change();
                }
            
                if (Schema::hasColumn($tableName, 'deleted_at')) {
                    $table->timestamp('deleted_at', 3)->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
