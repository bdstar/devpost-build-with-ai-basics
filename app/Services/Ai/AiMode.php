<?php

namespace App\Services\Ai;

/**
 * The AI mode in effect: AI_MODE from .env, unless the user switched this session to Demo Fixtures.
 * Switching only ever goes towards fixtures; Live AI still needs AI_MODE=live and a key in .env.
 */
class AiMode
{
    public const SESSION_KEY = 'ai_mode_override';

    public static function configured(): string
    {
        return config('ai.mode') === 'live' ? 'live' : 'fixtures';
    }

    public static function current(): string
    {
        if (app()->bound('session') && session(self::SESSION_KEY) === 'fixtures') {
            return 'fixtures';
        }

        return self::configured();
    }

    public static function isOverridden(): bool
    {
        return self::configured() === 'live' && self::current() === 'fixtures';
    }
}
