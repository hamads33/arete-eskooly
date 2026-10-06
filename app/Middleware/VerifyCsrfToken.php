<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class VerifyCsrfToken
{
    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!CSRF::validate((string) $request->input('_token', ''))) {
            // PHP drops the whole form (token included) when a post is bigger than post_max_size.
            $tooLarge = $request->all() === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
            if (str_contains((string) $request->header('Accept', ''), 'application/json')) {
                return Response::json(['ok' => false, 'reason' => $tooLarge ? 'too_large' : 'csrf', 'message' => $tooLarge
                    ? 'This upload is larger than the server accepts in one go (' . ini_get('post_max_size') . 'B). Remove some pages and try again.'
                    : 'Your session expired. Reload the page and try again.'], 419);
            }
            return Response::html($tooLarge ? 'The upload was too large. Go back, remove some files and try again.' : 'CSRF token mismatch.', 419);
        }
        return $next($request);
    }
}
