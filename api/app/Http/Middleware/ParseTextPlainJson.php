<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ParseTextPlainJson
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $contentType = $request->header('Content-Type', '');

        // 檢查是否為 text/plain 且包含 JSON 數據
        if (str_contains($contentType, 'text/plain')) {
            $rawContent = $request->getContent();

            if (!empty($rawContent)) {
                $jsonData = json_decode($rawContent, true);

                // 如果成功解析為 JSON 陣列，則合併到 request 中
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $request->merge($jsonData);

                    // 可選：設置正確的 Content-Type header
                    $request->headers->set('Content-Type', 'application/json');
                }
            }
        }

        return $next($request);
    }
}
