<?php

namespace App\Utilities;

class ProxyRequest
{
    //（バリデーション済みの）ログイン認証情報をセットしたのち、そのログイン情報を使って実際のトークン要求を行うmakePostRequestメソッドへと処理を移譲する。
    public function grantPasswordToken(string $email, string $password)
    {
        $params = [
            'grant_type' => 'password',//認証方式（パスワード認証）の指定
            'username' => $email,
            'password' => $password,
        ];

        return $this->makePostRequest($params);
    }

    /*リフレッシュトークンが問題なく送られてきていることを確認したのち、
    リフレッシュトークンによる新しいトークンの要求に必要な情報（$params）をセット。
    しかるのち、そのリフレッシュトークンを用いて実際にトークン要求を行うmakePostRequestメソッドへと処理を移譲する。*/
    public function refreshAccessToken()
    {
        // リクエストで送られてきたクッキーから'refresh_token'の値を取得する。
        $refreshToken = request()->cookie('refresh_token');

        /*refreshTokenがなければ、エラーを返す。
        （refreshTokenにはsetHttpOnlyCookieメソッドにより有効期限が設定したうえで、フロントに送っているため、有効期限が切れていた場合消滅している）
        refreshTokenが有効期限切れで消滅してしまっておりエラーが返された場合、ユーザーに再度ログインしてもらうようにフロントで設定しておく。
        */ 
        abort_unless($refreshToken, 403, 'Your refresh token is expired.');//※

        $params = [
            'grant_type' => 'refresh_token',//認証方式（リフレッシュトークンによる認証）の指定
            'refresh_token' => $refreshToken,
        ];

        return $this->makePostRequest($params);
    }

    //実際にトークン要求を出し、受け取ったリフレッシュトークンをhttpyonly cookieとしてセットする＋トークン要求結果を呼び出し元へと返すメソッド
    protected function makePostRequest(array $params)
    {
        /*トークンを受け取る権限を持つクライアントであることを確かめるためのクライアント認証に必要な、client_id,'client_secret'をセットすることで、「自分はトークンを取得する権限があるクライアントである」ということを知らせる
        第二引数$params：トークン発行するにあたってまずやっておかなければならないユーザー認証のための認証情報(+認証方式を指定するための情報)
        これら二つのarrayをmergeして一つにして新たな$paramsを作成*/
        $params = array_merge([
            'client_id' => config('services.passport.password_client_id'),
            'client_secret' => config('services.passport.password_client_secret'),
            'scope' => '*',
        ], $params);
        //dd($params);

        // トークン要求（postメソッドで、トークン発行を担うものがあるパス'oauth/token'に、トークンを発行するにあたっての認証に必要となる上で作成した$paramsを渡す）
        $proxy = \Request::create('/oauth/token', 'post', $params);
        // $tokenRequest = Request::create('/oauth/token', 'post', $data);

        // dd($proxy);

        // トークン要求の結果の中身である$proxy（Jsonデータ）をphpで扱える構造のデータにでコードし、その中身を取得する。
        $resp = json_decode(app()->handle($proxy)->getContent());

        /* refresh_tokenだけ、HttpOnlyCookieとしてセット（queue）する。
        （queueしたcookieはミドルウェア（\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class）によって、
        すべての処理が終わってクライアントにリスポンスを返す際にそのリスポンスに付与される）*/   
        $this->setHttpOnlyCookie($resp->refresh_token);
        // 認証結果を呼び出しもとに返す。（認証結果を加工して、クライアントへと返す処理は呼び指し元でなされる）
        return $resp;
    }

    //refreshTokenを入れたhttpyonly cookieをセットするメソッド
    protected function setHttpOnlyCookie(string $refreshToken)
    {
        /* queueしたcookieはミドルウェア（\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class）によって、
        すべての処理が終わってクライアントにリスポンスを返す際に、まとめてそのリスポンスに付与される*/
        cookie()->queue(
            'refresh_token',//cookie名
            $refreshToken,//cookieの値
            14400, // cookieの有効期限（分）：一日1440分だから、10 days（指定しない場合ブラウザが閉じるまでが有効期限となる）
            null,//ドメイン名（nullの場合はこのサーバーのドメイン）
            null,//パス(nullの場合は/)
            false,//secure(httpsのときのみ、Cookieを送信する。httpsとなる本番環境ではtrue
            true // httponly(httponlyを)
        );
    }
}