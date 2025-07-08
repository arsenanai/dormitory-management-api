<?php

namespace App\Http\Controllers;

use App\Services\MessageService;
use Illuminate\Http\Request;

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
			'title'            => 'required|string|max:255',
			'content'          => 'required|string',
			'recipient_type'   => 'required|in:all,dormitory,room,individual',
			'dormitory_id'     => 'required_if:recipient_type,dormitory|nullable|integer|exists:dormitories,id',
			'room_id'          => 'required_if:recipient_type,room|nullable|integer|exists:rooms,id',
			'recipient_ids'    => 'required_if:recipient_type,individual|nullable|array',
			'recipient_ids.*'  => 'integer|exists:users,id',
			'send_immediately' => 'sometimes|boolean',
		] );

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
			'title'           => 'sometimes|string|max:255',
			'content'         => 'sometimes|string',
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
		return response()->noContent();
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
		return $this->messageService->getUserMessages();
	}

	/**
	 * Mark message as read
	 */
	public function markAsRead( $id ) {
		return $this->messageService->markAsRead( $id );
	}
}
