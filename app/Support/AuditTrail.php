<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Http\Request;

class AuditTrail
{
    public static function record(Request $request, string $action, ?object $subject = null, array $details = []): void
    {
        AuditEvent::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->id,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
