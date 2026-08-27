<?php

namespace Mdbtq\PageViews\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mdbtq\PageViews\PageViewRecorder;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    public function __construct(private readonly PageViewRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Run after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->recorder->record($request, $response);
    }
}
