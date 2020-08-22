<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\User;
use App\Models\SupportTicket;

class SupportTicketPolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return true;
    }

    public function list(User $user)
    {
        return true;
    }

    public function read(User $user, SupportTicket $supportTicket)
    {
        return $user->id === $supportTicket->created_by_user_id;
    }

    public function update(User $user, SupportTicket $supportTicket)
    {
        return $user->id === $supportTicket->created_by_user_id;
    }

    public function delete(User $user, SupportTicket $supportTicket)
    {
        return $user->id === $supportTicket->created_by_user_id;
    }
}
