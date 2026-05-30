<?php

namespace Afterburner\Meetings\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class CalendarFeedToken
{
    public static function generate(User $user, Team $team): string
    {
        return Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{user_id: int, team_id: int}|null
     */
    public static function resolve(string $token): ?array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload)
            || ! isset($payload['user_id'], $payload['team_id'])
            || ! is_int($payload['user_id'])
            || ! is_int($payload['team_id'])) {
            return null;
        }

        return $payload;
    }

    public static function feedUrl(User $user, Team $team): string
    {
        return route('teams.meetings.calendar.feed', [
            'team' => $team->id,
            'token' => self::generate($user, $team),
        ]);
    }

    public static function webcalUrl(User $user, Team $team): string
    {
        return preg_replace('/^https?:\/\//', 'webcal://', self::feedUrl($user, $team)) ?? self::feedUrl($user, $team);
    }
}
