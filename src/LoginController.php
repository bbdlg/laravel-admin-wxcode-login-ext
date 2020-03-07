<?php
/**
 * Created by PhpStorm.
 * User: lsshu
 * Date: 2019/8/29
 * Time: 17:32
 */
namespace Lsshu\LaravelAdminWxcodeLoginExt;
use Encore\Admin\Controllers\AuthController;
use Illuminate\Support\Arr;
use Lsshu\LaravelAdminWxcodeLoginExt\Models\WechatUserInfo;
use Illuminate\Http\Request;
use Lsshu\Wechat\Service;
class LoginController extends AuthController
{
    use StoreTrait;

    /**
     * 配置 admin 配置
     * @param string $path
     */
    protected function overrideConfig($path="admin")
    {
        if($path!="admin"){
            $config = require config("admin.extensions.multitenancy.$path");
            config(['admin' => $config]);
            config(Arr::dot(config('admin.auth', []), 'auth.'));
        }
    }

    /**
     * 授权登录
     * @param string $path
     * @return bool|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    protected function authLogin($path = 'admin')
    {
        $config = config('admin.account',[]);
        $account = Service::account($config);
        if( !session()->has('wx_openid') ){
            /*记录当前地址*/
            session(['wx_current_url'=>url()->full()]);
            // 未登录
            $redirect =$account->getAuthorizeBaseInfo(
                route(config('code_login.route.name.authorize_callback','code_authorize_callback'),['path'=>$path]),
                ((isset($this->weLoginType) && $this->weLoginType ==='snsapi_base') || (config('code_login.login_type') && config('code_login.login_type')==='snsapi_base'))?'snsapi_base':'snsapi_userinfo'
            );
            return redirect($redirect);
        }
        return true;
    }

    /**
     * 判断是否已经授权登录
     * @return bool
     */
    protected function authCheckLogin()
    {
        return ! session()->has('wx_openid');
    }

    /**
     * 微信基本登录回调
     * @param Request $request
     * @param  $path
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function code_authorize_callback(Request $request, $path)
    {
        $this->overrideConfig($path);
        $config = config('admin.account',[]);
        $account = Service::account($config);
        $data = $request->all();
        /*获取openid*/
        $result = $account->getAuthorizeUserOpenId($data['code']);
        if(isset($result['scope']) && $result['scope'] == 'snsapi_userinfo'){
            $result = $account->getAuthorizeUserInfoByAccessToken($result);
            $result['user']['path'] = $path;
            session(['wx_openid'=>$result['user']['openid'],'wx_user'=>$result['user']]);
            try{
                WechatUserInfo::updateOrCreate(['openid'=>$result['user']['openid']],$result['user']);
            }catch (Exception $exception){}
        }elseif(isset($result['openid'])){
            /*保存登录信息*/
            session(['wx_openid'=>$result['openid']]);
        }else{
            exit('<h2>授权配置不正确！</h2>');
        }
        /*返回登录前页面*/
        $current_url = session('wx_current_url');
        return redirect($current_url);
    }
}