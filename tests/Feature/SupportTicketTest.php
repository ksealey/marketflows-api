<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use \App\Models\Agent;
use \App\Models\SupportTicket;
use \App\Models\SupportTicketComment;
use \App\Models\SupportTicketAttachment;
use \App\Mail\SupportTicketCreated;
use \App\Mail\SupportTicketCommentCreated;
use Mail;
use Storage;

class SupportTicketTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test list
     * 
     * @group support-tickets
     */
    public function testList()
    {
        factory(SupportTicket::class, 10)->create([
            'created_by_user_id' => $this->user->id
        ]);

        $response = $this->json('GET', route('list-support-tickets', [
            'date_type' => 'ALL_TIME'
        ]));
        $response->assertStatus(200);

        $response->assertJSON([
            "result_count"  => 10,
            "limit"         => 250,
            "page"          => 1,
            "total_pages"   =>  1,
            "next_page"     => null,
        ]);

        $response->assertJSONStructure([
            "results" => [
                [
                    'subject',
                    'description',
                    'status'
                ]
            ]
        ]);
    }

    /**
     * Test create
     * 
     * @group support-tickets
     */
    public function testCreate()
    {
        Mail::fake();

        $ticket = factory(SupportTicket::class)->make();
        $response = $this->json('POST', route('create-support-ticket', [
            'urgency'   => $ticket->urgency,
            'subject' => $ticket->subject,
            'description' => $ticket->description
        ]));

        $response->assertStatus(201);
        $response->assertJSON([
            'subject'     => $ticket->subject,
            'description' => $ticket->description,
            'status'      => SupportTicket::STATUS_UNASSIGNED,
            'comments'    => []
        ]);

        Mail::assertQueued(SupportTicketCreated::class, function($mail){
            return $mail->user->id == $this->user->id;
        });
    }

    /**
     * Test read
     * 
     * @group support-tickets
     */
    public function testRead()
    {
        $ticket = factory(SupportTicket::class)->create([
            'created_by_user_id' => $this->user->id
        ]);

        $comment = factory(SupportTicketComment::class)->create([
            'created_by_user_id' => $this->user->id,
            'support_ticket_id'  => $ticket->id
        ]);

        $response = $this->json('GET', route('read-support-ticket', [
            'supportTicket' => $ticket->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'urgency'     => $ticket->urgency,
            'subject'     => $ticket->subject,
            'description' => $ticket->description,
            'status'      => $ticket->status,
            'comments'    => [
                $comment->toArray()
            ]
        ]);
    }

    /**
     * Test closing a support ticket
     * 
     * @group support-tickets
     */
    public function testClosingSupportTicket()
    {
        $ticket = factory(SupportTicket::class)->create([
            'created_by_user_id' => $this->user->id
        ]);

        $response = $this->json('PUT', route('close-support-ticket', [
            'supportTicket' => $ticket->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Closed'
        ]);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'closed_by' => $this->user->full_name,
            'status'    => SupportTicket::STATUS_CLOSED
        ]);
    }

    /**
     * Test create comment
     * 
     * @group support-tickets
     */
    public function testCreateComment()
    {
        Mail::fake();

        $agent = factory(Agent::class)->create();

        $ticket = factory(SupportTicket::class)->create([
            'created_by_user_id' => $this->user->id,
            'agent_id' => $agent->id
        ]);

        $comment = factory(SupportTicketComment::class)->make();

        $response = $this->json('POST', route('create-support-ticket-comment', [
            'comment'       => $comment->comment,
            'supportTicket' => $ticket->id
        ]));

        $response->assertStatus(201);
        $response->assertJSON([
            'created_by_user_id' => $this->user->id,
            'comment' => $comment->comment
        ]);

        Mail::assertQueued(SupportTicketCommentCreated::class, function($mail) use($agent){
            return $mail->user->id == $this->user->id && $mail->agent->id == $agent->id;
        });
        
    }

    /**
     * Test create attachment
     * 
     * @group support-tickets
     */
    public function testCreateAttachment()
    {
        Storage::fake();

        $fileName   = 'evidence.jpg';
        $file       = UploadedFile::fake()->image($fileName);
        $agent      = factory(Agent::class)->create();
        $ticket     = factory(SupportTicket::class)->create([
                            'created_by_user_id' => $this->user->id,
                            'agent_id'           => $agent->id
                      ]);

        $response = $this->json('POST', route('create-support-ticket-attachment', [
            'supportTicket' => $ticket->id
        ]), [
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $expectedPath = 'support_tickets/' . $ticket->id . '/attachments/' . $file->hashName();
        $response->assertJSON([
            'created_by_user_id' => $this->user->id,
            'file_name'          => $fileName,
            'support_ticket_id'  => $ticket->id,
            'file_mime_type'     => 'image/jpeg',
            'path'               => $expectedPath
        ]);

        Storage::assertExists($expectedPath);
    }
}
