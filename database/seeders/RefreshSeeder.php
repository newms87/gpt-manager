<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        // all tables in the database
        $tables = DB::select('SHOW TABLES');

        foreach($tables as $table) {
            $table = get_object_vars($table);
            $table = array_values($table);
            $table = $table[0];

            if ($table === 'migrations') {
                continue;
            }

            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        $this->call([DatabaseSeeder::class]);
    }
}
