<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Contracts\MeetingReferenceProvider;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

class MeetingReferenceRegistry
{
    /** @var array<string, MeetingReferenceProvider> */
    protected array $providers = [];

    public function register(MeetingReferenceProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    /**
     * @return array<string, MeetingReferenceProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return array<string, MeetingReferenceProvider>
     */
    public function available(): array
    {
        return array_filter(
            $this->providers,
            fn (MeetingReferenceProvider $provider) => $provider->isAvailable()
        );
    }

    public function get(string $key): ?MeetingReferenceProvider
    {
        return $this->providers[$key] ?? null;
    }

    public function forModel(Model $reference): ?MeetingReferenceProvider
    {
        foreach ($this->available() as $provider) {
            if ($reference instanceof ($provider->modelClass())) {
                return $provider;
            }
        }

        return null;
    }

    public function resolveReference(string $key, Team $team, int $referenceId): ?Model
    {
        $provider = $this->get($key);

        if (! $provider || ! $provider->isAvailable()) {
            return null;
        }

        $modelClass = $provider->modelClass();

        return $modelClass::query()
            ->where('team_id', $team->id)
            ->find($referenceId);
    }
}
