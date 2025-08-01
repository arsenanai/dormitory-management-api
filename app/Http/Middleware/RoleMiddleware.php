<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware {
	public function handle( Request $request, Closure $next, ...$roles ): Response {
		$user = $request->user();

		if ( ! $user ) {
			return response()->json( [ 'message' => 'Unauthenticated' ], 401 );
		}

		// Load the role relationship if not already loaded
		if (!$user->relationLoaded('role')) {
			$user->load('role');
		}

		// Handle both comma-separated and multiple arguments
		$allowedRoles = [];
		foreach ($roles as $roleParam) {
			$allowedRoles = array_merge($allowedRoles, explode(',', $roleParam));
		}
		
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