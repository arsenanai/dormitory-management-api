<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware {
	public function handle( Request $request, Closure $next, string $roles ): Response {
		$user = $request->user();

		if ( ! $user ) {
			return response()->json( [ 'message' => 'Unauthenticated' ], 401 );
		}

		// Split roles by comma and check if user has any of them
		$allowedRoles = explode( ',', $roles );
		$hasRole = false;

		foreach ( $allowedRoles as $role ) {
			if ( $user->hasRole( trim( $role ) ) ) {
				$hasRole = true;
				break;
			}
		}

		if ( ! $hasRole ) {
			return response()->json( [ 'message' => 'Forbidden' ], 403 );
		}

		return $next( $request );
	}
}