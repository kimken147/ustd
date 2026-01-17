<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetLocale
{
    /**
     * 支援的語言列表
     *
     * @var array
     */
    protected $supportedLocales = ['zh_CN', 'en', 'th'];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $this->determineLocale($request);
        
        // 設定應用程式的語言
        App::setLocale($locale);

        return $next($request);
    }

    /**
     * 決定要使用的語言
     * 優先順序：1. Header (Accept-Language 或 X-Locale) 2. Query Parameter (locale) 3. User 設定 4. 預設語言
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function determineLocale(Request $request): string
    {
        // 1. 優先檢查 X-Locale Header（自訂 Header）
        if ($request->hasHeader('X-Locale')) {
            $locale = $request->header('X-Locale');
            if ($this->isSupportedLocale($locale)) {
                return $locale;
            }
        }

        // 2. 檢查 Accept-Language Header
        if ($request->hasHeader('Accept-Language')) {
            $locale = $this->parseAcceptLanguage($request->header('Accept-Language'));
            if ($locale && $this->isSupportedLocale($locale)) {
                return $locale;
            }
        }

        // 3. 檢查 Query Parameter
        if ($request->has('locale')) {
            $locale = $request->query('locale');
            if ($this->isSupportedLocale($locale)) {
                return $locale;
            }
        }

        // 4. 檢查已登入使用者的語言設定（如果有 user 表且有 language 欄位）
        if (Auth::check() && Auth::user()->language) {
            $locale = Auth::user()->language;
            if ($this->isSupportedLocale($locale)) {
                return $locale;
            }
        }

        // 5. 使用應用程式預設語言
        return config('app.locale', 'zh_CN');
    }

    /**
     * 檢查是否為支援的語言
     *
     * @param  string  $locale
     * @return bool
     */
    protected function isSupportedLocale(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales);
    }

    /**
     * 解析 Accept-Language Header
     * 例如：zh-CN,zh;q=0.9,en;q=0.8
     *
     * @param  string  $acceptLanguage
     * @return string|null
     */
    protected function parseAcceptLanguage(string $acceptLanguage): ?string
    {
        $locales = explode(',', $acceptLanguage);
        
        foreach ($locales as $locale) {
            // 移除品質值（q=0.9）
            $locale = trim(explode(';', $locale)[0]);
            
            // 將 zh-CN 轉換為 zh_CN
            $locale = str_replace('-', '_', $locale);
            
            // 檢查完整語言碼
            if ($this->isSupportedLocale($locale)) {
                return $locale;
            }
            
            // 檢查語言前綴（例如 zh）
            $prefix = explode('_', $locale)[0];
            foreach ($this->supportedLocales as $supported) {
                if (strpos($supported, $prefix) === 0) {
                    return $supported;
                }
            }
        }
        
        return null;
    }
}

