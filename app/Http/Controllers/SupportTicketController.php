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
    public function list(Request $request)
    {
        
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'subject'     => 'bail|required|max:255',
            'description' => 'bail|required|max:1024'
        ]);
        
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();

        $supportTicket = SupportTicket::create([
            'created_by_user_id' => $user->id,
            'subject'            => $request->subject,
            'description'        => $request->description,
            'status'             => SupportTicket::STATUS_UNASSIGNED
        ]);

        //  Mail to user and support
        Mail::to($user)
            ->cc(config('mail.to.support.address'))
            ->queue(new SupportTicketCreatedMail($user, $supportTicket));

        $supportTicket->comments = [];

        return response($supportTicket, 201);
    }

    public function read(Request $request, SupportTicket $supportTicket)
    {
        $supportTicket->comments = $supportTicket->comments;
        
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
            //  Upload file
            $file     = $request->file;
            $filePath = Storage::putFile('support_tickets/' . $supportTicket->id . '/attachments' , $file);

            //  Log in database
            $attachment = SupportTicketAttachment::create([
                'support_ticket_id'     => $supportTicket->id,
                'file_name'             => $file->getClientOriginalName(),
                'file_size'             => $file->getSize(),
                'file_mime_type'        => $file->getMimeType(),
                'path'                  => $filePath,
                'created_by_user_id'    => $request->user()->id
            ]);
        }catch(Exception $e){
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        return response($attachment, 201);
    }
}
