<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller {
	public function __construct( private DashboardService $dashboardService ) {
	}

	/**
	 * Get dashboard statistics
	 */
	public function index( Request $request ) {
		return $this->dashboardService->getDashboardStats();
	}

	/**
	 * Get dashboard statistics for a specific dormitory (for admins)
	 */
	public function dormitoryStats( $dormitoryId ) {
		return $this->dashboardService->getDormitoryStats( $dormitoryId );
	}
}
