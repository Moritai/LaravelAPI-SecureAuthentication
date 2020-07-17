<?php

namespace App\Http\Controllers;

use App\Utilities\ProxyRequest;//フロントの代理としてトークン要求を行うため読み込む
use App\Http\Requests\UserRegisterRequest; //バリデーションのためのフォームリクエストクラスの読み込み
use App\Http\Requests\UserLoginRequest; //バリデーションのためのフォームリクエストクラスの読み込み
use Illuminate\Support\Facades\Hash;//パスワードのハッシュ化のために読み込む
use App\User;//下記のUserモデルを介してusersテーブルを操作するのに使用

class AuthController extends Controller
{
    protected $proxy;

    public function __construct(ProxyRequest $proxy)
    {
        $this->proxy = $proxy;//ProxyRequestクラスの初期化
    }

    public function register(UserRegisterRequest $request)
    // まずフォームリクエストクラス（UserRegisterRequest）で定義したバリデーションを行い、クリアした場合の値（$request）がここでの引数となる
    {
        // Userモデルを介して、usersテーブルにデータを保存
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        //何らかの理由でuser登録ができなかった場合にエラーメッセージを返す。 
        if(!$user){
            return response()->json(['sucsess'=>false, "message"=>'Registration failed']);
        }

        //フロントの代理人である$this->proxyによって、トークン要求を行う（詳細はapp\Utilities\ProxyRequest.php）
        $resp = $this->proxy->grantPasswordToken(
            $user->email,
            $request->password//config/authのapi設定で'hash' => false,
        );
    
        //トークン要求が成功し、結果取得したアクセストークン情報の必要な部分だけ取り出してフロントに返す。
        // リフレッシュトークンはhttponly cookieに入れられるため、ここには含めない。
        return response([
            'token' => $resp->access_token,
            'expiresIn' => $resp->expires_in,//※
            'message' => 'Your account has been created',
        ], 201);
    }
    

    public function login(UserLoginRequest $request)
    {
        // まずフォームリクエストクラス（UserLoginRequest）で定義したバリデーションを行い、クリアした場合の値（$request）がここでの引数となる
        
        //Userモデルを介して、usersテーブルからユーザーが入力したemailと一致するemailが保存されている行のデータを取得
        $user = User::where('email', $request->email)->first();
        
        // 該当するuser情報がなければ、エラーを返す。
        abort_unless($user, 404, 'This combination does not exists.');

        abort_unless(
            /*ハッシュ化したユーザーが入力したパスワードと、ハッシュ化されてユーザーテーブルに保存されているパスワードが同じかチェックする
            その結果がfalseだった場合エラーを返す*/
            \Hash::check($request->password, $user->password),
            403,
            'This combination does not exists.'
        );
    
         //フロントの代理人である$this->proxyによって、トークン要求を行う（詳細はapp\Utilities\ProxyRequest.php）
        $resp = $this->proxy
            ->grantPasswordToken(request('email'), request('password'));
    
        //トークン要求が成功し、結果取得したアクセストークン情報の必要な部分だけ取り出してフロントに返す。
        // リフレッシュトークンはhttponly cookieに入れられるため、ここには含めない。
        return response([
            'token' => $resp->access_token,
            'expiresIn' => $resp->expires_in,
            'message' => 'You have been logged in',
        ], 200);
     }

     public function refreshToken()
     {
        //フロントの代理人である$this->proxyによって、トークンのリフレッシュの要求を行う（詳細はapp\Utilities\ProxyRequest.php）  
         $resp = $this->proxy->refreshAccessToken();
     
        //トークン要求が成功し、結果取得したアクセストークン情報の必要な部分だけ取り出してフロントに返す。
        // リフレッシュトークンはhttponly cookieに入れられるため、ここには含めない。
         return response([
             'token' => $resp->access_token,
             'expiresIn' => $resp->expires_in,
             'message' => 'Token has been refreshed.',
         ], 200);
     }

     public function logout()
     {
        // ここに関してはフロントの実装の際に再度調べる 
         $token = request()->user()->token();
         $token->delete();
     
         // remove the httponly cookie
         cookie()->queue(cookie()->forget('refresh_token'));
     
         return response([
             'message' => 'You have been successfully logged out',
         ], 200);
     }
     
}