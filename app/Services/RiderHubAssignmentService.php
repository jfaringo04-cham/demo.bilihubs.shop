<?php

namespace App\Services;

use App\Models\Hub;
use App\Models\User;
use RuntimeException;

class RiderHubAssignmentService
{
    public static function zoneForHub(Hub $hub): ?string
    {
        $zone = filled($hub->barangay)
            ? $hub->barangay
            : $hub->city;

        return filled($zone) ? trim((string) $zone) : null;
    }

    public static function assign(User $rider, Hub $hub): void
    {
        if (!$rider->roles()->where('name', 'rider')->exists()) {
            throw new RuntimeException(
                'Selected user is not a rider.'
            );
        }

        if ($rider->logistic_status !== 'approved') {
            throw new RuntimeException(
                'Only approved riders can be assigned to a hub.'
            );
        }

        if ($rider->status !== User::STATUS_ACTIVE) {
            throw new RuntimeException(
                'Only active riders can be assigned to a hub.'
            );
        }

        if (
            !$rider->logistic_id ||
            (int) $rider->logistic_id !== (int) $hub->logistic_id
        ) {
            throw new RuntimeException(
                'Rider and hub must belong to the same logistics company.'
            );
        }

        if (
            $rider->hub_id !== null &&
            (int) $rider->hub_id !== (int) $hub->id
        ) {
            throw new RuntimeException(
                'Rider is already assigned to another hub.'
            );
        }

        $rider->update([
            'hub_id' => $hub->id,
            'assigned_zone' => self::zoneForHub($hub),
        ]);
    }

    public static function syncRiderToCurrentHub(User $rider): void
    {
        if (!$rider->roles()->where('name', 'rider')->exists()) {
            return;
        }

        if (!$rider->hub_id) {
            return;
        }

        $hub = Hub::find($rider->hub_id);

        if (!$hub) {
            return;
        }

        if (
            !$rider->logistic_id ||
            (int) $rider->logistic_id !== (int) $hub->logistic_id
        ) {
            return;
        }

        $expectedZone = self::zoneForHub($hub);

        if ($rider->assigned_zone !== $expectedZone) {
            $rider->update([
                'assigned_zone' => $expectedZone,
            ]);

            $rider->assigned_zone = $expectedZone;
        }
    }

    public static function syncHubRiderZones(Hub $hub): int
    {
        return User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('logistic_id', $hub->logistic_id)
            ->where('hub_id', $hub->id)
            ->update([
                'assigned_zone' => self::zoneForHub($hub),
            ]);
    }

    public static function unassign(User $rider): void
    {
        if (!$rider->roles()->where('name', 'rider')->exists()) {
            throw new RuntimeException(
                'Selected user is not a rider.'
            );
        }

        $rider->update([
            'hub_id' => null,
            'assigned_zone' => null,
        ]);
    }
}