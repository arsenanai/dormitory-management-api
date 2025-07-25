<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Configuration;

class ConfigurationSeeder extends Seeder {
	public function run() {
		$configs = [ 
			// Common configs
			[ 'key' => 'max_students_per_dormitory', 'value' => '500' ],
			[ 'key' => 'backup_list_enabled', 'value' => '1' ],
			[ 'key' => 'registration_enabled', 'value' => '1' ],
			// Development-specific
			[ 'key' => 'dev_mode', 'value' => '1' ],
			// Production-specific (can be overridden in prod)
			[ 'key' => 'prod_mode', 'value' => '0' ],
		];
		foreach ( $configs as $config ) {
			Configuration::updateOrCreate( [ 'key' => $config['key'] ], [ 'value' => $config['value'] ] );
		}
	}
}