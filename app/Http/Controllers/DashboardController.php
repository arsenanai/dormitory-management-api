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

	/**
	 * Get guard dashboard
	 */
	public function guardDashboard( Request $request ) {
		return $this->dashboardService->getGuardStats();
	}

	/**
	 * Get student dashboard
	 */
	public function studentDashboard( Request $request ) {
		return $this->dashboardService->getStudentDashboardStats();
	}

	/**
	 * Get guest dashboard
	 */
	public function guestDashboard( Request $request ) {
		return $this->dashboardService->getGuestDashboardStats();
	}

	/**
	 * Get monthly stats
	 */
	public function monthlyStats( Request $request ) {
		return $this->dashboardService->getMonthlyStats();
	}

	/**
	 * Get payment analytics
	 */
	public function paymentAnalytics( Request $request ) {
		return $this->dashboardService->getPaymentAnalytics();
	}
}
