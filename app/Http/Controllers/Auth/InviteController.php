<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Auth\UserInvite;
use App\Mail\Auth\UserInvite as UserInviteEmail;
use Validator;
use Mail;

class InviteController extends Controller
{
    /**
     * Invite a new user to your company
     * 
     * @param Illuminate\Http\Request $request
     * 
     * @return Illuminate\Http\Response
     */
    public function invite(Request $request)
    {
        $rules = [
            'email' => 'bail|required|email|unique:users,email'
        ];

        $messages = [
            'email.required' => 'Email required',
            'email.email'    => 'Email invalid',
            'email.unique'   => 'Email in use'
        ];

        $validator = Validator::make($request->input(), $rules, $messages);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 400);
        }

        $userInvite = UserInvite::create([
            'invited_by' => $request->user()->id,
            'email'      => $request->email,
            'key'        => str_random(40),
            'expires_at' => now()->addDays(30)
        ]);

        Mail::to($userInvite->email)
            ->later(now(), new UserInviteEmail($userInvite));

        return response([
            'message' => 'success',
            'ok'      => true
        ]);
    }

    /**
     * Invite a new user to your company
     * 
     * @param Illuminate\Http\Request $request
     * @param int $inviteId 
     * 
     * @return Illuminate\Http\Response
     */
    public function deleteInvite(Request $request, int $inviteId)
    {
        $invite = UserInvite::find($inviteId);
        if( ! $invite ){
            return response([
                'error' => 'Invite not found',
                'ok'    => false
            ], 404);
        }

        //  Make sure the user deleting the invite has to do so
        $invitedBy = User::find($invite->invited_by)->withTrashed();
        if( $invitedBy->company_id != $request->user()->id ){
            return response([
                'error' => 'Invite not found',
                'ok'    => false
            ], 404);
        }

        $invite->delete();

        return response([
            'message' => 'success',
            'ok'      => true
        ]);
    }
}
