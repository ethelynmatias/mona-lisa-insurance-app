<?php

namespace App\Services;

use App\Models\AppLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class DatabaseLogger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        try {
            $requestId = self::getRequestId();
            
            AppLog::create([
                'level' => $level,
                'channel' => $context['channel'] ?? 'application',
                'message' => $message,
                'context' => $context,
                'logged_at' => now(),
                'user_id' => Auth::id(),
                'session_id' => session()->getId(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'request_id' => $requestId,
                'form_id' => $context['form_id'] ?? null,
                'webhook_log_id' => $context['webhook_log_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Fallback to regular Laravel logging if database fails
            \Log::channel('single')->error('DatabaseLogger failed: ' . $e->getMessage());
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    private static function getRequestId(): string
    {
        static $requestId = null;
        
        if ($requestId === null) {
            $requestId = Str::random(12);
        }
        
        return $requestId;
    }
}