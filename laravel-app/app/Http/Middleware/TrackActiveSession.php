<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackActiveSession
{
    public function handle(Request $request, Closure $next)
    {
        $sessionId = $request->getSession()->getId();
        if ($sessionId) {
            // remove stale entries older than 40 minutes
            DB::table('active_sessions')->where('last_activity', '<', now()->subMinutes(40))->delete();

            // update or insert current session record when user is logged in
            $role = session('role');
            $userId = session('student_id') ?? session('admin_id') ?? session('scanner_id');

            DB::table('active_sessions')->updateOrInsert(
                ['session_id' => $sessionId],
                [
                    'role' => $role,
                    'user_id' => $userId,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'last_activity' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return $next($request);
    }
}
