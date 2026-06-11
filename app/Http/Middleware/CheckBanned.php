<?php
// app/Http/Middleware/CheckBanned.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->is_banned) {
            return response()->json(['message' => 'Akun kamu telah diblokir.'], 403);
        }

        return $next($request);
    }
}