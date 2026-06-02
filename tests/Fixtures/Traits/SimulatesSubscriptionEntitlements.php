<?php

namespace App\Traits;

trait SimulatesSubscriptionEntitlements
{
    /**
     * @var array<string, mixed>
     */
    protected array $simulatedPlanFeatures = [];

    protected bool $simulatedActiveSubscription = false;

    /**
     * @param  array<string, mixed>  $features
     */
    public function simulatePlanFeatures(array $features): static
    {
        $this->simulatedPlanFeatures = $features;

        return $this;
    }

    public function simulateActiveSubscription(bool $active = true): static
    {
        $this->simulatedActiveSubscription = $active;

        return $this;
    }

    public function onGenericTrial(): bool
    {
        $endsAt = $this->trial_ends_at ?? null;

        if ($endsAt === null) {
            return false;
        }

        return $endsAt->isFuture();
    }

    public function hasEntitlement(string $featureSlug): bool
    {
        $features = $this->simulatedPlanFeatures['features'] ?? [];

        if (! is_array($features)) {
            return false;
        }

        return in_array($featureSlug, $features, true);
    }

    public function withinEntitlementLimit(string $key, int $current): bool
    {
        $limit = $this->simulatedPlanFeatures[$key] ?? null;

        if ($limit === null || $limit === '') {
            return true;
        }

        return $current <= (int) $limit;
    }

    public function hasActiveSubscription(): bool
    {
        if ($this->simulatedActiveSubscription) {
            return true;
        }

        return $this->onGenericTrial();
    }
}
