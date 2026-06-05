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
        $url = route('teams.meetings.calendar.feed', [
            'teamId' => $team->id,
        ], absolute: true).'?token='.rawurlencode(self::generate($user, $team));

        return self::ensureHttps($url);
    }

    public static function webcalUrl(User $user, Team $team): string
    {
        $url = self::feedUrl($user, $team);

        return preg_replace('/^https:\/\//', 'webcal://', $url) ?? $url;
    }

    protected static function ensureHttps(string $url): string
    {
        if (! str_starts_with($url, 'http://')) {
            return $url;
        }

        if (request()->isSecure() || config('afterburner-meetings.calendar.feed_force_https', false)) {
            return 'https://'.substr($url, 7);
        }

        return $url;
    }
}
