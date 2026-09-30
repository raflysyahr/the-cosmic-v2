<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Enums\CpSource;
use App\Modules\Discuss\Models\CpEvent;
use App\Modules\Discuss\Services\Concerns\AuthorizesCpAdmin;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CpEventService
{
    use AuthorizesCpAdmin;

    /**
     * Event multiplier yang berlaku untuk source + room ini sekarang.
     * Kalau beberapa event aktif bersamaan, dipakai yang terbesar (TIDAK
     * ditumpuk) supaya dua event "x3" tidak tiba-tiba jadi x9.
     */
    public function resolveFor(CpSource $source, ?string $roomId): ?CpEvent
    {
        $best = null;

        foreach (CpEvent::running()->get() as $event) {
            if (! $event->appliesTo($source->value, $roomId)) {
                continue;
            }
            if ($best === null || $event->multiplier_pct > $best->multiplier_pct) {
                $best = $event;
            }
        }

        return $best;
    }

    /** Event yang sedang berlangsung — untuk banner di frontend (semua user login). */
    public function active(): array
    {
        return CpEvent::running()->orderBy('ends_at')->get()
            ->map(fn (CpEvent $event) => $this->present($event))
            ->values()
            ->all();
    }

    public function list(User $actor): array
    {
        $this->assertCpAdmin($actor);

        return CpEvent::orderBy('starts_at', 'desc')->get()
            ->map(fn (CpEvent $event) => $this->present($event))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): array
    {
        $this->assertCpAdmin($actor);

        $attributes = $this->attributes($data);
        $this->assertValidWindow($attributes['starts_at'], $attributes['ends_at']);

        $event = CpEvent::create($attributes + [
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actor->id,
        ]);

        return $this->present($event);
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, string $eventId, array $data): array
    {
        $this->assertCpAdmin($actor);

        $event = CpEvent::findOrFail($eventId);
        $attributes = $this->attributes($data);

        if (array_key_exists('is_active', $data)) {
            $attributes['is_active'] = (bool) $data['is_active'];
        }

        $this->assertValidWindow(
            $attributes['starts_at'] ?? $event->starts_at,
            $attributes['ends_at'] ?? $event->ends_at,
        );

        $event->update($attributes);

        return $this->present($event->fresh());
    }

    public function delete(User $actor, string $eventId): void
    {
        $this->assertCpAdmin($actor);

        CpEvent::findOrFail($eventId)->delete();
    }

    public function present(CpEvent $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'multiplier' => $event->multiplier_pct / 100,
            'sources' => $event->sources,
            'room_id' => $event->room_id,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'is_active' => $event->is_active,
            'is_running' => $event->is_active
                && $event->starts_at <= now()
                && $event->ends_at > now(),
        ];
    }

    /** Hanya key yang dikirim yang ikut (supaya update parsial tidak menimpa dengan null). */
    private function attributes(array $data): array
    {
        $attributes = [];

        if (array_key_exists('name', $data)) {
            $attributes['name'] = $data['name'];
        }
        if (array_key_exists('multiplier', $data)) {
            $attributes['multiplier_pct'] = (int) round(((float) $data['multiplier']) * 100);
        }
        if (array_key_exists('sources', $data)) {
            $attributes['sources'] = $data['sources'] === [] ? null : $data['sources'];
        }
        if (array_key_exists('room_id', $data)) {
            $attributes['room_id'] = $data['room_id'];
        }
        if (array_key_exists('starts_at', $data)) {
            $attributes['starts_at'] = Carbon::parse($data['starts_at']);
        }
        if (array_key_exists('ends_at', $data)) {
            $attributes['ends_at'] = Carbon::parse($data['ends_at']);
        }

        return $attributes;
    }

    private function assertValidWindow(mixed $startsAt, mixed $endsAt): void
    {
        if ($endsAt <= $startsAt) {
            throw ValidationException::withMessages([
                'ends_at' => ['The event must end after it starts.'],
            ]);
        }
    }
}
