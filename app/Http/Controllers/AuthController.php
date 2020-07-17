<?php

namespace App\Http\Controllers;

use App\Utilities\ProxyRequest;//フロントの代理としてトークン要求を行うため読み込む
use App\Http\Requests\UserRegisterRequest; //バリデーションのためのフォームリクエストクラスの読み込み
use Illuminate\Support\Facades\Hash;//パスワードのハッシュ化のために読み込む
use App\User;//下記のUser::createに使用

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
    
        //トークン要求の結果取得したアクセストークン情報の必要な部分だけ取り出してフロントに返す。
        // リフレッシュトークンはhttponly cookieに入れられるため、ここには含めない。
        return response([
            'token' => $resp->access_token,
            'expiresIn' => $resp->expires_in,//※
            'message' => 'Your account has been created',
        ], 201);
    }
    

    public function login()
    {
    }

    public function refreshTo()
    {
    }

    public function logout()
    {
    }
}