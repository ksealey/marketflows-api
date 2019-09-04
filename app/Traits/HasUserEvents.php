<?php

namespace App\Traits;

use \App\Models\User;
use \App\Models\UserEvent;
use Illuminate\Database\Eloquent\Model;

trait HasUserEvents
{
    /**
     * Log a user event
     * 
     * 
     */
    public function logUserEvent(User $user, string $event, Model $existingResource, ?Model $newResource = null)
    {
        list($resourceGroup, $eventType) = explode('.', $event);

        $changes = [];
        if( $newResource ){
            $oldData = $existingResource->toArray();
            $newData = $newResource->toArray();
            foreach( $oldData as $field => $value ){
                if( $oldData[$field] == $newData[$field] )
                    continue;

                $changes[] = [
                    'field'         => $field,
                    'from_value'    => $oldData[$field],
                    'to_value'      => isset($newData[$field]) ? $newData[$field] : null
                ];
            }
        }

        if( $eventType == 'update' && count($changes) == 0 )
            return null;

        return UserEvent::create([
            'user_id'       => $user->id,
            'event'         => $event,
            'resource_id'   => $existingResource->id,
            'changes'       => count($changes) ? json_encode($changes) : null
        ]);
    }
}
