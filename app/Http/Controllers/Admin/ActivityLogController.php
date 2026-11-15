<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'area' => ['nullable', 'string', 'max:60'],
            'outcome' => ['nullable', Rule::in(array_keys(AdminActivityLog::OUTCOMES))],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $timezone = 'Asia/Manila';
        $query = AdminActivityLog::query();
        foreach (['actor_id', 'area', 'outcome'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (isset($filters['date'])) {
            $day = Carbon::parse($filters['date'], $timezone)->startOfDay();
            $query->where('occurred_at', '>=', $day->copy()->setTimezone(config('app.timezone')))
                ->where('occurred_at', '<', $day->addDay()->setTimezone(config('app.timezone')));
        }

        $entries = $query->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(25)->appends($filters);
        $areas = AdminActivityLog::query()->distinct()->orderBy('area')->pluck('area');
        $outcomes = AdminActivityLog::OUTCOMES;

        return view('admin.activity-log', compact('entries', 'areas', 'outcomes', 'filters', 'timezone'));
    }
}
