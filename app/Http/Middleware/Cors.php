<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // 許可するオリジンの設定
        $allowedOrigins = [
            'http://localhost:3000', '*'
        ];
        // リクエストが来たオリジン
        $requestOrigin = $request->headers->get('origin');
    
        // リクエストのオリジンが許可するオリジンの中に含まれていれば、処理した結果のデータに正しいヘッダーをつけて、返す
        if (in_array($requestOrigin, $allowedOrigins)) {
            return $next($request)
                ->header('Access-Control-Allow-Origin', $requestOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    
            
        // 許可したオリジンでなければエラーを返す。
        return response()->json(['sucsess'=>false, "message"=>'Origin Error']);
    }
}
