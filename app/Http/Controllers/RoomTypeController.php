<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use App\Services\RoomTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomTypeController extends Controller {
	private $rules = [
		'name'    => 'required|in:standard,lux',
		'minimap' => 'sometimes|image',
		'beds'    => 'sometimes|json',
	];
	
	public function __construct( private RoomTypeService $service ) {}

	public function index( Request $request ) {
		$roomTypes = $this->service->listRoomTypes();
		return response()->json( $roomTypes, 200 );
	}

	public function store( Request $request ) {
		$validated = $request->validate( $this->rules );
		if ( $request->hasFile( 'minimap' ) ) {
			$validated['minimap'] = $request->file( 'minimap' )->store( 'minimaps', 'public' );
		}

		if ( isset( $validated['beds'] ) ) {
			$validated['beds'] = json_decode( $validated['beds'], true );
		}

		$roomType = $this->service->createRoomType( $validated );
		return response()->json( $roomType, 201 );
	}

	public function update( Request $request, $id ) {
		$roomType = $this->service->findRoomType( $id );
		$validated = $request->validate( $this->rules );

		if ( $request->hasFile( 'minimap' ) ) {
			$validated['minimap'] = $request->file( 'minimap' )->store( 'minimaps', 'public' );
		}

		if ( isset( $validated['beds'] ) ) {
			$validated['beds'] = json_decode( $validated['beds'], true );
		}

		$roomType = $this->service->updateRoomType( $roomType, $validated );
		return response()->json( $roomType, 200 );
	}

	public function destroy( $id ) {
		$this->service->deleteRoomType( $id );
		return response()->noContent();
	}
}