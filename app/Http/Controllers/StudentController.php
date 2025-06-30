<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class StudentController extends Controller
{
	public function approve( $id ) {
		$student = User::where( 'id', $id )
			->whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->firstOrFail();

		$student->status = 'active';
		$student->save();

		return response()->json( [ 'message' => 'student.approved.' ], 200 );
	}
}
