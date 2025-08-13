<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder {
	/**
	 * Run the database seeds.
	 */
	public function run(): void {
		// Create standard room type
		RoomType::firstOrCreate(
			[ 'name' => 'standard' ],
			[ 
				'capacity' => 2,
				'price'    => 150.00,
				'minimap'  => null,
				'beds'     => json_encode( [ 
							[ 'x' => 10, 'y' => 20, 'width' => 30, 'height' => 40 ],
							[ 'x' => 50, 'y' => 60, 'width' => 30, 'height' => 40 ],
						] ),
				'photos'   => json_encode( [] ),
			]
		);

		// Create lux room type
		RoomType::firstOrCreate(
			[ 'name' => 'lux' ],
			[ 
				'capacity' => 1,
				'price'    => 300.00,
				'minimap'  => null,
				'beds'     => json_encode( [ 
							[ 'x' => 10, 'y' => 20, 'width' => 30, 'height' => 40 ],
						] ),
				'photos'   => json_encode( [] ),
			]
		);
	}
}
