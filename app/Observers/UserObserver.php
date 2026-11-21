<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserSearchToken;

class UserObserver
{
    /**
     * Handle the User "saved" event to sync name search tokens.
     */
    public function saved(User $user): void
    {
        if ($user->wasChanged('full_name') || ! $user->searchTokens()->exists()) {
            $this->syncSearchTokens($user);
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $user->searchTokens()->delete();
    }

    /**
     * Sync name blind index tokens for the given user.
     */
    public function syncSearchTokens(User $user): void
    {
        $fullName = (string) $user->full_name;
        $tokens = function_exists('name_search_tokens') ? name_search_tokens($fullName) : [];

        // Delete existing tokens
        UserSearchToken::where('user_id', $user->id)->delete();

        if (empty($tokens)) {
            return;
        }

        $now = now();
        $records = [];
        foreach ($tokens as $tokenBidx) {
            $records[] = [
                'user_id' => $user->id,
                'token_bidx' => $tokenBidx,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        UserSearchToken::insert($records);
    }
}
