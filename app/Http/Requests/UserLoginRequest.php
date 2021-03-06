<?php
 
namespace App\Http\Requests;
 
use Illuminate\Foundation\Http\FormRequest;
 
use Illuminate\Contracts\Validation\Validator;//バリデーションエラーの際Jsonでエラー内容をクライアントに返すために追加
use Illuminate\Http\Exceptions\HttpResponseException;//バリデーションエラーの際Jsonでエラー内容をクライアントに返すために追加
 
class UserLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
 
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'required|email',
            'password' => 'required|between:6,20'
        ];
    }
 
//ここ！
    //ここでmessageメソッドを以下のように定義することで、自分で明示的にエラーメッセージの書き換えを行わずとも、自動的にここで定義したエラーメッセージがデフォルトのエラーメッセージに上書きされる。
    public function messages()
    {
        return [
            'email.required' => 'メールアドレスは必須です。',
            'email.email' => 'メールアドレスが正しくありません。',
            'password.required' => 'パスワードは必須です。',
            'password.between' => 'パスワードはは6文字以上20文字以下で入力して下さい。',
        ];
    }
 
    // FormRequest::failedValidation() をオーバーライドでエラーの際のリスポンスデータをJsonに書き換える。
    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}