<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJournalAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowed = match ($ability) {
            'journal.submit' => $user->canJournalSubmit(),
            'journal.review' => $user->canJournalReview(),
            'journal.assign' => $user->canJournalAssign(),
            'journal.publish' => $user->canJournalPublish(),
            default => false,
        };

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
