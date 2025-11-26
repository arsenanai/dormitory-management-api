<?php

namespace App\Http\Controllers;

use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller {
	public function __construct( private MessageService $messageService ) {
	}

	/**
	 * Display a listing of messages
	 */
	public function index( Request $request ) {
		$filters = $request->validate( [ 
			'recipient_type' => 'sometimes|in:all,dormitory,room,individual',
			'dormitory_id'   => 'sometimes|integer|exists:dormitories,id',
			'room_id'        => 'sometimes|integer|exists:rooms,id',
			'status'         => 'sometimes|in:draft,sent,read',
			'per_page'       => 'sometimes|integer|min:1|max:100',
		] );

		return $this->messageService->getMessagesWithFilters( $filters );
	}

	/**
	 * Store a newly created message
	 */
	public function store( Request $request ) {
		$validated = $request->validate( [ 
			'receiver_id'      => 'required|integer|exists:users,id',
			'title'            => 'required|string|max:255',
			'content'          => 'required|string',
			'type'             => 'required|in:general,announcement,violation,emergency,urgent',
			'recipient_type'   => 'sometimes|in:all,dormitory,room,individual',
			'dormitory_id'     => 'nullable|integer|exists:dormitories,id',
			'room_id'          => 'nullable|integer|exists:rooms,id',
			'recipient_ids'    => 'nullable|array',
			'recipient_ids.*'  => 'integer|exists:users,id',
			'send_immediately' => 'sometimes|boolean',
		] );

		// Set default recipient_type if not provided
		if ( ! isset( $validated['recipient_type'] ) ) {
			$validated['recipient_type'] = 'individual';
		}

		return $this->messageService->createMessage( $validated );
	}

	/**
	 * Display the specified message
	 */
	public function show( $id ) {
		return $this->messageService->getMessageDetails( $id );
	}

	/**
	 * Update the specified message
	 */
	public function update( Request $request, $id ) {
		$validated = $request->validate( [ 
			'receiver_id'     => 'sometimes|integer|exists:users,id',
			'title'           => 'sometimes|string|max:255',
			'content'         => 'sometimes|string',
			'type'            => 'sometimes|in:general,announcement,violation,emergency,urgent',
			'recipient_type'  => 'sometimes|in:all,dormitory,room,individual',
			'dormitory_id'    => 'sometimes|nullable|integer|exists:dormitories,id',
			'room_id'         => 'sometimes|nullable|integer|exists:rooms,id',
			'recipient_ids'   => 'sometimes|nullable|array',
			'recipient_ids.*' => 'integer|exists:users,id',
		] );

		return $this->messageService->updateMessage( $id, $validated );
	}

	/**
	 * Remove the specified message
	 */
	public function destroy( $id ) {
		$this->messageService->deleteMessage( $id );
		return response()->json( [ 'message' => 'Message deleted successfully' ], 200 );
	}

	/**
	 * Send a message
	 */
	public function send( $id ) {
		return $this->messageService->sendMessage( $id );
	}

	/**
	 * Get messages for the authenticated user (students)
	 */
	public function myMessages( Request $request ) {
		$perPage = $request->get( 'per_page', 20 );
		$result = $this->messageService->getUserMessages( $perPage, $request->get('query') );
		return $result;
	}

	/**
	 * Mark message as read
	 */
	public function markAsRead( $id ) {
		return $this->messageService->markAsRead( $id );
	}

	/**
	 * Get unread messages count for the authenticated user
	 */
	public function unreadCount() {
		return $this->messageService->getUnreadCount();
	}
}
