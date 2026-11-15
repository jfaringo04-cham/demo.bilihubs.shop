<?php

namespace App\Http\Middleware;

use App\Models\AdminActivityLog;
use Closure;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordAdminActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $request->routeIs('admin.notifications.*')) {
            return $next($request);
        }

        $actor = $request->user();
        $routeName = $request->route()->getName();
        $parts = explode('.', (string) $routeName, 3);
        if (!$actor || count($parts) !== 3 || $parts[0] !== 'admin') {
            return $next($request);
        }

        [, $area, $action] = $parts;
        if ($routeName === 'admin.refund-disputes.update'
            && in_array($request->input('action'), ['review', 'request_proof', 'resolve'], true)) {
            $action = $request->input('action');
        }

        $targets = [];
        foreach ($request->route()->parameters() as $name => $target) {
            $id = $target instanceof UrlRoutable ? $target->getRouteKey() : $target;
            if ((is_int($id) || is_string($id)) && (ctype_digit((string) $id) || Str::isUuid((string) $id))) {
                $targets[$name] = (string) $id;
            }
        }

        $entry = [
            'actor_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'occurred_at' => now(),
            'area' => $area,
            'action' => $action,
            'targets' => $targets,
        ];

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $status = match (true) {
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ValidationException => $exception->status,
                default => 500,
            };
            $this->record($entry, $status, $status >= 500 ? 'failed' : 'rejected');

            throw $exception;
        }

        $status = $response->getStatusCode();
        $flash = $request->session()->get('_flash.new', []);
        $outcome = match (true) {
            $status >= 500 => 'failed',
            $status >= 400 || in_array('errors', $flash, true) || in_array('error', $flash, true) => 'rejected',
            $status < 300 || in_array('success', $flash, true) => 'completed',
            default => 'redirected',
        };

        $this->record($entry, $status, $outcome);

        return $response;
    }

    private function record(array $entry, int $status, string $outcome): void
    {
        try {
            AdminActivityLog::create($entry + ['http_status' => $status, 'outcome' => $outcome]);
        } catch (Throwable $exception) {
            Log::error('Could not save admin activity', [
                'actor_id' => $entry['actor_id'],
                'area' => $entry['area'],
                'action' => $entry['action'],
                'http_status' => $status,
                'exception_type' => $exception::class,
            ]);
        }
    }
}
