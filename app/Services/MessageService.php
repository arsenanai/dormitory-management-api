<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use App\Models\Dormitory;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;

class MessageService {
	/**
	 * Get messages with filters and pagination
	 */
	public function getMessagesWithFilters( array $filters = [] ) {
		$query = Message::with( [ 'sender', 'receiver', 'dormitory', 'room' ] );

		$user = Auth::user();

		// If user is a student, only show their messages
		if ( $user && $user->hasRole( 'student' ) ) {
			$query->where( 'receiver_id', $user->id );
		}

		// Apply filters
		if ( isset( $filters['recipient_type'] ) ) {
			$query->where( 'recipient_type', $filters['recipient_type'] );
		}

		if ( isset( $filters['dormitory_id'] ) ) {
			$query->where( 'dormitory_id', $filters['dormitory_id'] );
		}

		if ( isset( $filters['room_id'] ) ) {
			$query->where( 'room_id', $filters['room_id'] );
		}

		if ( isset( $filters['status'] ) ) {
			$query->where( 'status', $filters['status'] );
		}

		$perPage = $filters['per_page'] ?? 20;

		return response()->json( $query->orderBy( 'created_at', 'desc' )->paginate( $perPage ) );
	}

	/**
	 * Create a new message
	 */
	public function createMessage( array $data ) {
		$data['sender_id'] = Auth::id();
		$data['status'] = 'draft';

		// Handle recipient IDs for individual messages
		if ( isset( $data['recipient_ids'] ) ) {
			$data['recipient_ids'] = json_encode( $data['recipient_ids'] );
		}

		$message = Message::create( $data );

		// If send_immediately is true, send the message right away
		if ( isset( $data['send_immediately'] ) && $data['send_immediately'] ) {
			$this->sendMessage( $message->id );
			$message->refresh(); // Reload the message to get updated status
		}

		return response()->json( $message->load( [ 'sender', 'receiver', 'dormitory', 'room' ] ), 201 );
	}

	/**
	 * Get message details
	 */
	public function getMessageDetails( $id ) {
		$message = Message::with( [ 'sender', 'receiver', 'dormitory', 'room' ] )->findOrFail( $id );

		$user = Auth::user();

		// If user is a student, only allow viewing their own messages
		if ( $user && $user->hasRole( 'student' ) && $message->receiver_id !== $user->id ) {
			return response()->json( [ 'message' => 'Forbidden' ], 403 );
		}

		// Decode recipient_ids if it exists
		if ( $message->recipient_ids ) {
			$message->recipient_ids = json_decode( $message->recipient_ids, true );
		}

		return response()->json( $message );
	}

	/**
	 * Update message
	 */
	public function updateMessage( $id, array $data ) {
		$message = Message::findOrFail( $id );

		// Only allow updates if message is still in draft
		if ( $message->status !== 'draft' ) {
			return response()->json( [ 'error' => 'Cannot update sent messages' ], 422 );
		}

		// Handle recipient IDs for individual messages
		if ( isset( $data['recipient_ids'] ) ) {
			$data['recipient_ids'] = json_encode( $data['recipient_ids'] );
		}

		$message->update( $data );

		return response()->json( $message->load( [ 'sender', 'dormitory', 'room' ] ) );
	}

	/**
	 * Delete message
	 */
	public function deleteMessage( $id ) {
		$message = Message::findOrFail( $id );

		// Only allow deletion if message is still in draft
		if ( $message->status !== 'draft' ) {
			return response()->json( [ 'error' => 'Cannot delete sent messages' ], 422 );
		}

		$message->delete();
		return response()->json( [ 'message' => 'Message deleted successfully' ], 200 );
	}

	/**
	 * Send a message
	 */
	public function sendMessage( $id ) {
		$message = Message::findOrFail( $id );

		if ( $message->status !== 'draft' ) {
			return response()->json( [ 'error' => 'Message has already been sent' ], 422 );
		}

		$message->status = 'sent';
		$message->sent_at = now();
		$message->save();

		return response()->json( [ 
			'message' => 'Message sent successfully',
			'data'    => $message->load( [ 'sender', 'dormitory', 'room' ] )
		] );
	}

	/**
	 * Get messages for the authenticated user
	 */
	public function getUserMessages() {
		$user = Auth::user();

		$query = Message::where( 'status', 'sent' )
			->where( function ($q) use ($user) {
				// Messages for all students
				$q->where( 'recipient_type', 'all' );
				
				// Messages for user's dormitory (only if user has a room with dormitory)
				if ($user->room && $user->room->dormitory_id) {
					$q->orWhere( function ($subQ) use ($user) {
						$subQ->where( 'recipient_type', 'dormitory' )
							->where( 'dormitory_id', $user->room->dormitory_id );
					} );
				}
				
				// Messages for user's room (only if user has a room)
				if ($user->room_id) {
					$q->orWhere( function ($subQ) use ($user) {
						$subQ->where( 'recipient_type', 'room' )
							->where( 'room_id', $user->room_id );
					} );
				}
				
				// Individual messages - PostgreSQL compatible approach for TEXT JSON fields
				$q->orWhere( function ($subQ) use ($user) {
					$subQ->where( 'recipient_type', 'individual' )
						->where( function ($innerQ) use ($user) {
							// Use LIKE queries that work with TEXT fields containing JSON
							$innerQ->where( 'recipient_ids', 'LIKE', '%"' . $user->id . '"%' )
								->orWhere( 'recipient_ids', 'LIKE', '%' . $user->id . '%' );
						} );
				} );
			} )
			->with( [ 'sender' ] )
			->orderBy( 'sent_at', 'desc' );

		return response()->json( $query->paginate( 20 ) );
	}

	/**
	 * Mark message as read
	 */
	public function markAsRead( $id ) {
		$message = Message::findOrFail( $id );
		$message->update( [ 'read_at' => now() ] );
		return response()->json( [ 'message' => 'Message marked as read' ] );
	}

	/**
	 * Get unread messages count for the authenticated user
	 */
	public function getUnreadCount() {
		$user = Auth::user();

		$count = Message::where( function ($query) use ($user) {
			// Direct messages to this user
			$query->where( 'receiver_id', $user->id );

			// OR broadcast messages
			$query->orWhere( function ($subQuery) use ($user) {
				$subQuery->where( function ($q) use ($user) {
					// Messages for all students
					$q->where( 'recipient_type', 'all' );
				} )
					// Messages for specific dormitory
					->orWhere( function ($subQ) use ($user) {
						$subQ->where( 'recipient_type', 'dormitory' )
							->where( 'dormitory_id', $user->dormitory_id ?? 0 );
					} )
					// Messages for specific room
					->orWhere( function ($subQ) use ($user) {
						$subQ->where( 'recipient_type', 'room' )
							->where( 'room_id', $user->room_id );
					} )
					// Individual messages via recipient_ids - PostgreSQL compatible
					->orWhere( function ($subQ) use ($user) {
						$subQ->where( 'recipient_type', 'individual' )
							->where( 'recipient_ids', 'LIKE', '%"' . $user->id . '"%' );
					} );
			} );
		} )
			->whereNull( 'read_at' )
			->count();

		return response()->json( [ 'count' => $count ] );
	}
}
