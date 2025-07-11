<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Message;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class MessageControllerTest extends TestCase {
	use RefreshDatabase, WithFaker;

	private User $admin;
	private User $student;
	private User $guard;

	protected function setUp(): void {
		parent::setUp();

		// Create roles
		$adminRole = Role::create( [ 'name' => 'admin' ] );
		$studentRole = Role::create( [ 'name' => 'student' ] );
		$guardRole = Role::create( [ 'name' => 'guard' ] );

		// Create test users
		$this->admin = User::factory()->create( [ 
			'role_id'  => $adminRole->id,
			'email'    => 'admin@test.com',
			'password' => bcrypt( 'password123' ),
		] );

		$this->student = User::factory()->create( [ 
			'role_id'  => $studentRole->id,
			'email'    => 'student@test.com',
			'password' => bcrypt( 'password123' ),
		] );

		$this->guard = User::factory()->create( [ 
			'role_id'  => $guardRole->id,
			'email'    => 'guard@test.com',
			'password' => bcrypt( 'password123' ),
		] );
	}

	public function test_admin_can_view_all_messages() {
		// Create test messages
		$message1 = Message::factory()->create( [ 
			'sender_id'   => $this->student->id,
			'receiver_id' => $this->admin->id,
			'title'       => 'Test Message 1',
			'content'     => 'This is a test message',
			'type'        => 'general',
		] );

		$message2 = Message::factory()->create( [ 
			'sender_id'   => $this->guard->id,
			'receiver_id' => $this->admin->id,
			'title'       => 'Test Message 2',
			'content'     => 'Another test message',
			'type'        => 'violation',
		] );

		$response = $this->actingAs( $this->admin )
			->getJson( '/api/messages' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'data' => [ 
					'*' => [ 
						'id',
						'sender_id',
						'receiver_id',
						'title',
						'content',
						'type',
						'read_at',
						'created_at',
						'updated_at',
						'sender',
						'receiver',
					]
				]
			] );
	}

	public function test_student_can_view_own_messages() {
		// Create messages for the student
		$message1 = Message::factory()->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
			'title'       => 'Message to Student',
			'content'     => 'This is a message for the student',
			'type'        => 'general',
		] );

		// Create message for another student (should not be visible)
		$otherStudent = User::factory()->create( [ 'role_id' => $this->student->role_id ] );
		$message2 = Message::factory()->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $otherStudent->id,
			'title'       => 'Message to Other Student',
			'content'     => 'This is a message for another student',
			'type'        => 'general',
		] );

		$response = $this->actingAs( $this->student )
			->getJson( '/api/messages' );

		$response->assertStatus( 200 )
			->assertJsonCount( 1, 'data' )
			->assertJsonFragment( [ 
				'title'       => 'Message to Student',
				'receiver_id' => $this->student->id,
			] )
			->assertJsonMissing( [ 
				'title' => 'Message to Other Student',
			] );
	}

	public function test_admin_can_create_message() {
		$messageData = [ 
			'receiver_id' => $this->student->id,
			'title'       => 'New Message',
			'content'     => 'This is a new message content',
			'type'        => 'general',
		];

		$response = $this->actingAs( $this->admin )
			->postJson( '/api/messages', $messageData );

		$response->assertStatus( 201 )
			->assertJsonFragment( [ 
				'title'       => 'New Message',
				'content'     => 'This is a new message content',
				'type'        => 'general',
				'sender_id'   => $this->admin->id,
				'receiver_id' => $this->student->id,
			] );

		$this->assertDatabaseHas( 'messages', [ 
			'title'       => 'New Message',
			'content'     => 'This is a new message content',
			'type'        => 'general',
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
		] );
	}

	public function test_guard_can_create_violation_message() {
		$messageData = [ 
			'receiver_id' => $this->student->id,
			'title'       => 'Violation Report',
			'content'     => 'Student was found smoking in the room',
			'type'        => 'violation',
		];

		$response = $this->actingAs( $this->guard )
			->postJson( '/api/messages', $messageData );

		$response->assertStatus( 201 )
			->assertJsonFragment( [ 
				'title'       => 'Violation Report',
				'content'     => 'Student was found smoking in the room',
				'type'        => 'violation',
				'sender_id'   => $this->guard->id,
				'receiver_id' => $this->student->id,
			] );

		$this->assertDatabaseHas( 'messages', [ 
			'title'       => 'Violation Report',
			'type'        => 'violation',
			'sender_id'   => $this->guard->id,
			'receiver_id' => $this->student->id,
		] );
	}

	public function test_create_message_validation() {
		$response = $this->actingAs( $this->admin )
			->postJson( '/api/messages', [] );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'receiver_id', 'title', 'content', 'type' ] );
	}

	public function test_create_message_with_invalid_receiver() {
		$messageData = [ 
			'receiver_id' => 99999, // Non-existent user
			'title'       => 'Test Message',
			'content'     => 'Test content',
			'type'        => 'general',
		];

		$response = $this->actingAs( $this->admin )
			->postJson( '/api/messages', $messageData );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'receiver_id' ] );
	}

	public function test_admin_can_view_specific_message() {
		$message = Message::factory()->create( [ 
			'sender_id'   => $this->student->id,
			'receiver_id' => $this->admin->id,
			'title'       => 'Specific Message',
			'content'     => 'This is a specific message',
			'type'        => 'general',
		] );

		$response = $this->actingAs( $this->admin )
			->getJson( "/api/messages/{$message->id}" );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'id'      => $message->id,
				'title'   => 'Specific Message',
				'content' => 'This is a specific message',
			] );
	}

	public function test_student_can_view_own_message() {
		$message = Message::factory()->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
			'title'       => 'Student Message',
			'content'     => 'This is a message for student',
			'type'        => 'general',
		] );

		$response = $this->actingAs( $this->student )
			->getJson( "/api/messages/{$message->id}" );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'id'    => $message->id,
				'title' => 'Student Message',
			] );
	}

	public function test_student_cannot_view_other_student_message() {
		$otherStudent = User::factory()->create( [ 'role_id' => $this->student->role_id ] );
		$message = Message::factory()->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $otherStudent->id,
			'title'       => 'Other Student Message',
			'content'     => 'This is a message for another student',
			'type'        => 'general',
		] );

		$response = $this->actingAs( $this->student )
			->getJson( "/api/messages/{$message->id}" );

		$response->assertStatus( 403 );
	}

	public function test_admin_can_update_message() {
		$message = Message::factory()->draft()->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
			'title'       => 'Original Title',
			'content'     => 'Original content',
			'type'        => 'general',
		] );

		$updateData = [ 
			'title'   => 'Updated Title',
			'content' => 'Updated content',
			'type'    => 'urgent',
		];

		$response = $this->actingAs( $this->admin )
			->putJson( "/api/messages/{$message->id}", $updateData );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'title'   => 'Updated Title',
				'content' => 'Updated content',
				'type'    => 'urgent',
			] );

		$this->assertDatabaseHas( 'messages', [ 
			'id'      => $message->id,
			'title'   => 'Updated Title',
			'content' => 'Updated content',
			'type'    => 'urgent',
		] );
	}

	public function test_admin_can_delete_message() {
		$message = Message::factory()->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
			'title'       => 'Message to Delete',
			'content'     => 'This message will be deleted',
			'type'        => 'general',
			'status'      => 'draft', // Ensure it's a draft so it can be deleted
		] );

		$response = $this->actingAs( $this->admin )
			->deleteJson( "/api/messages/{$message->id}" );

		$response->assertStatus( 200 )
			->assertJson( [ 'message' => 'Message deleted successfully' ] );

		$this->assertSoftDeleted( 'messages', [ 
			'id' => $message->id,
		] );
	}

	public function test_student_cannot_delete_message() {
		$message = Message::factory()->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
			'title'       => 'Message to Keep',
			'content'     => 'This message should not be deleted',
			'type'        => 'general',
		] );

		$response = $this->actingAs( $this->student )
			->deleteJson( "/api/messages/{$message->id}" );

		$response->assertStatus( 403 );

		$this->assertDatabaseHas( 'messages', [ 
			'id'         => $message->id,
			'deleted_at' => null,
		] );
	}

	public function test_mark_message_as_read() {
		$message = Message::factory()->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
			'title'       => 'Unread Message',
			'content'     => 'This message is unread',
			'type'        => 'general',
			'read_at'     => null,
		] );

		$response = $this->actingAs( $this->student )
			->putJson( "/api/messages/{$message->id}/mark-read" );

		$response->assertStatus( 200 )
			->assertJson( [ 'message' => 'Message marked as read' ] );

		$this->assertDatabaseHas( 'messages', [ 
			'id' => $message->id,
		] );

		$message->refresh();
		$this->assertNotNull( $message->read_at );
	}

	public function test_get_unread_messages_count() {
		// Create unread messages
		Message::factory()->count( 3 )->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
			'read_at'     => null,
		] );

		// Create read message
		Message::factory()->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
			'read_at'     => now(),
		] );

		$response = $this->actingAs( $this->student )
			->getJson( '/api/messages/unread-count' );

		$response->assertStatus( 200 )
			->assertJson( [ 'count' => 3 ] );
	}

	public function test_unauthenticated_user_cannot_access_messages() {
		$response = $this->getJson( '/api/messages' );
		$response->assertStatus( 401 );
	}
}
