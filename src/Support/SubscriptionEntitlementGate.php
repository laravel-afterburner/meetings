<?php

namespace Afterburner\Meetings\Support;

use App\Support\Concerns\ChecksOptionalSubscriptionEntitlement;
use Illuminate\Database\Eloquent\Model;

final class SubscriptionEntitlementGate
{
    use ChecksOptionalSubscriptionEntitlement;

    public const FEATURE_SLUG = 'meetings';

    public static function allows(Model $team): bool
    {
        return static::allowsSubscriptionFeature($team, static::FEATURE_SLUG);
    }

    public static function withinLimit(Model $team, string $key, int $current): bool
    {
        return static::withinSubscriptionLimit($team, static::FEATURE_SLUG, $key, $current);
    }
}
