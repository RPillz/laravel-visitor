<?php

namespace RPillz\LaravelVisitor\Support;

use Illuminate\Http\Request;

class HeaderFingerprint
{
    private const BROWSER_SIGNALS = ['sec-fetch-site', 'sec-fetch-mode', 'sec-fetch-dest', 'sec-ch-ua'];

    public function compute(Request $request): string
    {
        $headerNames = array_keys($request->headers->all());
        sort($headerNames);

        return hash('sha256', implode('|', [
            $request->header('Accept', ''),
            $request->header('Accept-Language', ''),
            $request->header('Accept-Encoding', ''),
            implode(',', $headerNames),
        ]));
    }

    public function looksLikeBrowser(Request $request): bool
    {
        $score = 0;

        if ($request->header('Accept-Language')) {
            $score++;
        }

        if (str_contains($request->header('Accept', ''), 'text/html')) {
            $score++;
        }

        if (str_contains($request->header('Accept-Encoding', ''), 'gzip')) {
            $score++;
        }

        foreach (self::BROWSER_SIGNALS as $header) {
            if ($request->hasHeader($header)) {
                $score++;
                break;
            }
        }

        return $score >= 2;
    }
}
