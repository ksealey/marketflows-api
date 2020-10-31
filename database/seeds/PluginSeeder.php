<?php

use Illuminate\Database\Seeder;
use App\Models\Plugin;

class PluginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //  GA
        Plugin::create([
            'key'           => 'google-analytics',
            'name'          => 'Google Analytics',
            'details'       => 'Send your call data to google analytics as an event after a call ends.',
            'image_path'    => '/assets/images/plugins/google-analytics.png',
            'price'         => 0,
        ]);

        //  Webhooks
        Plugin::create([
            'key'           => 'webhooks',
            'name'          => 'Webhooks',
            'details'       => 'Send your call data anywhere over HTTP when a call comes in.',
            'image_path'    => '/assets/images/plugins/webhooks.png',
            'price'         => 0,
        ]);
    }
}
