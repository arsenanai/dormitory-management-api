<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class MessageServiceTest extends TestCase {
	use RefreshDatabase, WithFaker;

	private MessageService $messageService;
	private User $admin;
	private User $student;
	private User $guard;

	protected function setUp(): void {
		parent::setUp();

		$this->messageService = new MessageService();

		// Create roles
		$adminRole = Role::create( [ 'name' => 'admin' ] );
		$studentRole = Role::create( [ 'name' => 'student' ] );
		$guardRole = Role::create( [ 'name' => 'guard' ] );

		// Create test users
		$this->admin = User::factory()->create( [ 
			'role_id' => $adminRole->id,
			'email'   => 'admin@test.com',
		] );

		$this->student = User::factory()->create( [ 
			'role_id' => $studentRole->id,
			'email'   => 'student@test.com',
		] );

		$this->guard = User::factory()->create( [ 
			'role_id' => $guardRole->id,
			'email'   => 'guard@test.com',
		] );
	}

	public function test_get_messages_with_filters() {
		// Create messages with different recipient types
		$message1 = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'General Message',
			'content'        => 'Message for all',
			'recipient_type' => 'all',
			'status'         => 'sent',
		] );

		$message2 = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Individual Message',
			'content'        => 'Message for specific user',
			'recipient_type' => 'individual',
			'recipient_ids'  => json_encode( [ $this->student->id ] ),
			'status'         => 'sent',
		] );

		$filters = [ 'recipient_type' => 'all' ];
		$response = $this->messageService->getMessagesWithFilters( $filters );

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );
		$this->assertArrayHasKey( 'data', $responseData );
	}

	public function test_create_message() {
		Auth::login( $this->admin );

		$messageData = [ 
			'title'          => 'New Message',
			'content'        => 'This is a new message content',
			'recipient_type' => 'all',
		];

		$response = $this->messageService->createMessage( $messageData );

		$this->assertEquals( 201, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertEquals( 'New Message', $responseData['title'] );
		$this->assertEquals( 'This is a new message content', $responseData['content'] );
		$this->assertEquals( 'all', $responseData['recipient_type'] );
		$this->assertEquals( $this->admin->id, $responseData['sender_id'] );
		$this->assertEquals( 'draft', $responseData['status'] );
	}

	public function test_create_message_with_immediate_send() {
		Auth::login( $this->admin );

		$messageData = [ 
			'title'            => 'Immediate Message',
			'content'          => 'This message should be sent immediately',
			'recipient_type'   => 'all',
			'send_immediately' => true,
		];

		$response = $this->messageService->createMessage( $messageData );

		$this->assertEquals( 201, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertEquals( 'sent', $responseData['status'] );
		$this->assertNotNull( $responseData['sent_at'] );
	}

	public function test_get_message_details() {
		$message = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Test Message',
			'content'        => 'Test content',
			'recipient_type' => 'individual',
			'recipient_ids'  => json_encode( [ $this->student->id ] ),
		] );

		$response = $this->messageService->getMessageDetails( $message->id );

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertEquals( $message->id, $responseData['id'] );
		$this->assertEquals( $message->title, $responseData['title'] );
		$this->assertEquals( [ $this->student->id ], $responseData['recipient_ids'] );
	}

	public function test_get_nonexistent_message() {
		$this->expectException( ModelNotFoundException::class);
		$this->messageService->getMessageDetails( 99999 );
	}

	public function test_update_message() {
		$message = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Original Title',
			'content'        => 'Original content',
			'recipient_type' => 'all',
			'status'         => 'draft',
		] );

		$updateData = [ 
			'title'          => 'Updated Title',
			'content'        => 'Updated content',
			'recipient_type' => 'individual',
			'recipient_ids'  => [ $this->student->id ],
		];

		$response = $this->messageService->updateMessage( $message->id, $updateData );

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertEquals( 'Updated Title', $responseData['title'] );
		$this->assertEquals( 'Updated content', $responseData['content'] );
		$this->assertEquals( 'individual', $responseData['recipient_type'] );
	}

	public function test_cannot_update_sent_message() {
		$message = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Sent Message',
			'content'        => 'This message is already sent',
			'recipient_type' => 'all',
			'status'         => 'sent',
		] );

		$updateData = [ 
			'title' => 'Updated Title',
		];

		$response = $this->messageService->updateMessage( $message->id, $updateData );

		$this->assertEquals( 422, $response->status() );
		$responseData = json_decode( $response->getContent(), true );
		$this->assertEquals( 'Cannot update sent messages', $responseData['error'] );
	}

	public function test_delete_message() {
		$message = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Message to Delete',
			'content'        => 'This message will be deleted',
			'recipient_type' => 'all',
			'status'         => 'draft',
		] );

		$this->messageService->deleteMessage( $message->id );

		$this->assertSoftDeleted( 'messages', [ 
			'id' => $message->id,
		] );
	}

	public function test_cannot_delete_sent_message() {
		$message = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Sent Message',
			'content'        => 'This message is already sent',
			'recipient_type' => 'all',
			'status'         => 'sent',
		] );

		$response = $this->messageService->deleteMessage( $message->id );

		$this->assertEquals( 422, $response->status() );
		$responseData = json_decode( $response->getContent(), true );
		$this->assertEquals( 'Cannot delete sent messages', $responseData['error'] );
	}

	public function test_send_message() {
		$message = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Draft Message',
			'content'        => 'This message is in draft',
			'recipient_type' => 'all',
			'status'         => 'draft',
		] );

		$response = $this->messageService->sendMessage( $message->id );

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertEquals( 'Message sent successfully', $responseData['message'] );
		$this->assertEquals( 'sent', $responseData['data']['status'] );
		$this->assertNotNull( $responseData['data']['sent_at'] );
	}

	public function test_cannot_send_already_sent_message() {
		$message = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Already Sent Message',
			'content'        => 'This message is already sent',
			'recipient_type' => 'all',
			'status'         => 'sent',
		] );

		$response = $this->messageService->sendMessage( $message->id );

		$this->assertEquals( 422, $response->status() );
		$responseData = json_decode( $response->getContent(), true );
		$this->assertEquals( 'Message has already been sent', $responseData['error'] );
	}

	public function test_get_user_messages() {
		Auth::login( $this->student );

		// Create messages that the student should receive
		$message1 = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Message for All',
			'content'        => 'This is for everyone',
			'recipient_type' => 'all',
			'status'         => 'sent',
		] );

		$message2 = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Individual Message',
			'content'        => 'This is for specific student',
			'recipient_type' => 'individual',
			'recipient_ids'  => json_encode( [ $this->student->id ] ),
			'status'         => 'sent',
		] );

		// Create draft message (should not be included)
		$message3 = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Draft Message',
			'content'        => 'This is still a draft',
			'recipient_type' => 'all',
			'status'         => 'draft',
		] );

		$response = $this->messageService->getUserMessages();

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertArrayHasKey( 'data', $responseData );
		$this->assertCount( 2, $responseData['data'] );
	}

	public function test_mark_message_as_read() {
		$message = Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Unread Message',
			'content'        => 'This message is unread',
			'recipient_type' => 'all',
			'status'         => 'sent',
		] );

		$response = $this->messageService->markAsRead( $message->id );

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );
		$this->assertEquals( 'Message marked as read', $responseData['message'] );
	}
}
