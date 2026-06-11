<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    // Field yang tidak perlu di-sanitasi
    private array $except = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        $this->sanitize($input);
        $request->merge($input);

        return $next($request);
    }

    private function sanitize(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if (in_array($key, $this->except)) continue;

            if (is_array($value)) {
                $this->sanitize($value);
            } elseif (is_string($value)) {
                // Strip HTML tags & trim whitespace
                $value = strip_tags(trim($value));
            }
        }
    }
}