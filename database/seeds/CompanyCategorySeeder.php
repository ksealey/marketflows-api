<?php

use Illuminate\Database\Seeder;

class CompanyCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        \DB::table('company_categories')->insert([
            ['name' => 'Farmer\'s Market', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Local Farmer', 'created_at' => $now, 'updated_at' => $now]
        ]);
    }
}
