<?php

namespace App\Services;

use App\Models\Hub;
use App\Models\User;

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
        $rider->update([
            'hub_id' => $hub->id,
            'assigned_zone' => self::zoneForHub($hub),
        ]);
    }

    public static function syncRiderToCurrentHub(User $rider): void
    {
        if (!$rider->hub_id) {
            return;
        }

        $hub = Hub::find($rider->hub_id);

        if (!$hub) {
            return;
        }

        $expectedZone = self::zoneForHub($hub);

        if ($rider->assigned_zone !== $expectedZone) {
            $rider->update(['assigned_zone' => $expectedZone]);
            $rider->assigned_zone = $expectedZone;
        }
    }

    public static function syncHubRiderZones(Hub $hub): int
    {
        return User::query()
            ->where('hub_id', $hub->id)
            ->update([
                'assigned_zone' => self::zoneForHub($hub),
            ]);
    }

    public static function unassign(User $rider): void
    {
        $rider->update([
            'hub_id' => null,
            'assigned_zone' => null,
        ]);
    }
}
