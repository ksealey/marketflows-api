<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\SupportTicketAttachment;
use App\Mail\SupportTicketCreated as SupportTicketCreatedMail;
use App\Mail\SupportTicketCommentCreated as SupportTicketCommentCreatedMail;
use Validator;
use Mail;
use DB;
use Storage;
use Exception;

class SupportTicketController extends Controller
{
    public $fields = [
        'support_tickets.urgency',
        'support_tickets.subject',
        'support_tickets.status',
        'support_tickets.created_at',
        'support_tickets.updated_at'
    ];

    public function list(Request $request)
    {
        $user  = $request->user();
        $query = SupportTicket::select(['support_tickets.*', DB::raw("TRIM(CONCAT(agents.first_name, ' ', agents.last_name)) as agent_name")])
                                ->where('account_id', $user->account_id)
                                ->where('created_by_user_id', $user->id)
                                ->leftJoin('agents', 'agents.id', 'support_tickets.agent_id');

        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            [],
            $this->fields,
            'support_tickets.created_at'
        );
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'urgency'     => 'in:' . implode(',', SupportTicket::urgencies()),
            'subject'     => 'bail|required|max:255',
            'description' => 'bail|required|max:1024',
            'file'        => 'bail|file|max:10485760',
        ]);
        
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();

        DB::beginTransaction();

        try{
            $supportTicket = SupportTicket::create([
                'account_id'         => $user->account_id,
                'created_by_user_id' => $user->id,
                'urgency'            => $request->urgency,
                'subject'            => $request->subject,
                'description'        => $request->description,
                'status'             => SupportTicket::STATUS_UNASSIGNED
            ]);

            if( $request->file ){
                //  Upload file
                $file     = $request->file;
                $filePath = Storage::putFile('accounts/' . $user->account_id . '/support_tickets/' . $supportTicket->id . '/attachments' , $file, [
                    'visibility'          => 'public',
                    'ContentDisposition' => 'attachment; filename=' . $file->getClientOriginalName(),
                    'ContentType'        => 'application/octet-stream',
                    'AccessControlAllowOrigin' => '*'
                ]);

                //  Log in database
                $user = $request->user();
                $attachment = SupportTicketAttachment::create([
                    'account_id'            => $user->account_id,
                    'support_ticket_id'     => $supportTicket->id,
                    'file_name'             => $file->getClientOriginalName(),
                    'file_size'             => $file->getSize(),
                    'file_mime_type'        => $file->getMimeType(),
                    'path'                  => $filePath,
                    'created_by_user_id'    => $user->id
                ]);

                $supportTicket->attachments = [$attachment];
            }else{
                $supportTicket->attachments = [];
            }
        }catch(Exception $e){
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        //  Mail to user and support
        Mail::to($user)
            ->cc(config('mail.to.support.address'))
            ->queue(new SupportTicketCreatedMail($user, $supportTicket));

        $supportTicket->comments = [];

        return response($supportTicket, 201);
    }

    public function read(Request $request, SupportTicket $supportTicket)
    {
        $supportTicket->comments    = $supportTicket->comments;
        $supportTicket->attachments = $supportTicket->attachments;
        
        return response($supportTicket);
    }

    public function close(Request $request, SupportTicket $supportTicket)
    {
        if( $supportTicket->status == SupportTicket::STATUS_CLOSED ){
            return response([
                'error' => 'Ticket already closed'
            ], 400);
        }
        $supportTicket->closed_at = now();
        $supportTicket->closed_by = $request->user()->full_name;
        $supportTicket->status    = SupportTicket::STATUS_CLOSED;
        $supportTicket->save();

        $supportTicket->comments    = $supportTicket->comments;
        $supportTicket->attachments = $supportTicket->attachments;

        return response($supportTicket);
    }


    public function createComment(Request $request, SupportTicket $supportTicket)
    {
        $validator = Validator::make($request->input(), [
            'comment' => 'bail|required|max:1024',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user    = $request->user();
        $comment = SupportTicketComment::create([
            'account_id'         => $user->account_id,
            'support_ticket_id'  => $supportTicket->id,
            'comment'            => $request->comment,
            'created_by_user_id' => $user->id
        ]);

        if( $supportTicket->agent_id ){
            $agent = $supportTicket->agent;

            Mail::to($agent->email)
                ->queue(new SupportTicketCommentCreatedMail($user, $agent, $supportTicket, $comment));
        }

        return response($comment, 201);
    }

    public function createAttachment(Request $request, SupportTicket $supportTicket)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'bail|required|max:10485760|file',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        DB::beginTransaction();
        
        try{
            $user = $request->user();
            
            //  Upload file
            $file     = $request->file;
            $filePath = Storage::putFile('accounts/' . $user->account_id .  '/support_tickets/' . $supportTicket->id . '/attachments' , $file, [
                'visibility'         => 'public',
                'ContentDisposition' => 'attachment; filename=' . $file->getClientOriginalName(),
                'ContentType'        => 'application/octet-stream',
                'AccessControlAllowOrigin' => '*'
            ]);

            //  Log in database
            $attachment = SupportTicketAttachment::create([
                'account_id'            => $user->account_id,
                'support_ticket_id'     => $supportTicket->id,
                'file_name'             => $file->getClientOriginalName(),
                'file_size'             => $file->getSize(),
                'file_mime_type'        => $file->getMimeType(),
                'path'                  => $filePath,
                'created_by_user_id'    => $user->id
            ]);
        }catch(Exception $e){
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        return response($attachment, 201);
    }
}
