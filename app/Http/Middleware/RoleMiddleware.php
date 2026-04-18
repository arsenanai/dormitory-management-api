<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param \Closure $next
     * @param string ...$roles
     * @return Response
     */
    public function handle(Request $request, \Closure $next, string ...$roles): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json([ 'message' => 'Unauthenticated' ], 401);
        }

        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        $allowedRoles = [];
        foreach ($roles as $roleParam) {
            $allowedRoles = array_merge($allowedRoles, explode(',', $roleParam));
        }

        $hasRole = false;

        foreach ($allowedRoles as $role) {
            $trimmedRole = trim($role);
            if ($user->hasRole($trimmedRole)) {
                $hasRole = true;
                break;
            }
        }

        if (! $hasRole) {
            if (config('app.debug')) {
                $roleName = $user->role?->name;
                \Log::debug('RoleMiddleware: User ' . $user->id . ' with role ' . ($roleName ?? 'none') . ' denied access. Allowed roles: ' . implode(', ', $allowedRoles));
            }
            return response()->json([ 'message' => 'Forbidden' ], 403);
        }

        return $next($request);
    }
}
