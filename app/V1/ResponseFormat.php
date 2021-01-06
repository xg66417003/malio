<?php


namespace App\V1;


class ResponseFormat
{

    public static function normalResp($info,$data=null)
    {
        return base64_encode(Helper::rc4(json_encode(
            [
                'code' => 200,
                'info'=>$info,
                'data' => $data,
            ]
        )));
    }

    public static function errorResp($code,$info)
    {
        return base64_encode(Helper::rc4(json_encode(
            [
                'code' => $code,
                'info' => $info,
            ]
        )));
    }

    public static function unAuth()
    {
        return static::errorResp(401,'请先登录');
    }

    public static function forbid()
    {
        return static::errorResp(403, '您的账户已被禁用');
    }

    public static function badResp($msg)
    {
        return static::errorResp(400, $msg);
    }

}
