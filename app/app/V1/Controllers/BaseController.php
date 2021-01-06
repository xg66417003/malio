<?php


namespace App\V1\Controllers;



use App\Controllers\LinkController;
use App\Models\Ann;
use App\Models\LoginIp;
use App\Models\User;
use App\Services\Auth;
use App\Services\Config;
use App\Utils\Hash;
use App\Utils\URL;
use App\V1\ResponseFormat;
use function GuzzleHttp\Promise\exception_for;

class BaseController
{
    protected $user;

    public function __construct()
    {
        $this->user = Auth::getUser();
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    private static function getIniConfig($name=null,$default=null){
        static $config;
        if (! isset($config)){
            $config_file = dirname(__DIR__) . '/config.ini';
            $config = parse_ini_file($config_file);
        }
        if (!$name) return $config;
        return $config[$name] ?? $default;
    }

    public function login($request,$response)
    {

        $check = LoginIp::where([
            ['ip', $_SERVER['REMOTE_ADDR']],
            ['type', 1],
            ['datetime', '>', time() - 3600]
        ])->count();
        if ($check>25) {
            return $response->getBody()->write(ResponseFormat::badResp('您最近登录过于频繁，请1小时后重试'));
        }

        $email = $request->getParam('username');
        $email = trim($email);
        $email = strtolower($email);
        $passwd = $request->getParam('password');

        // Handle Login
        $user = User::where('email', '=', $email)->first();
        if ($user == null) {
            return $response->getBody()->write(ResponseFormat::badResp('用户名不存在'));
        }

        if (!Hash::checkPassword($user->pass, $passwd)) {
            $loginIP = new LoginIp();
            $loginIP->ip = $_SERVER['REMOTE_ADDR'];
            $loginIP->userid = $user->id;
            $loginIP->datetime = time();
            $loginIP->type = 1;
            $loginIP->save();
            return $response->getBody()->write(ResponseFormat::badResp('用户名或密码错误'));
        }

        $time = 3600 * 24 * 600;
        Auth::login($user->id, $time);
        $loginIP = new LoginIp();
        $loginIP->ip = $_SERVER['REMOTE_ADDR'];
        $loginIP->userid = $user->id;
        $loginIP->datetime = time();
        $loginIP->type = 0;
        $loginIP->save();

        $this->user = $user;
        return $response->getBody()->write(ResponseFormat::normalResp('登录成功',$this->getUserInfo()));
    }

    public function sublink($request,$response)
    {
        $newResponse = $response->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        if (method_exists(URL::class,'getNew_AllItems')){
             $Rule = [
                 'type'    => 'all',
                 'emoji'   => false,
                 'is_mu'   => 1
             ];
            $link = Url::get_NewAllUrl($this->user, $Rule);
            $link = $link?explode(PHP_EOL,$link):[];
        }else{
            $v2 = [];
            if(method_exists(URL::class,'getAllVMessUrl')){
                $v2 = array_filter(explode(PHP_EOL, URL::getAllVMessUrl($this->user)));
            }
            $link = array_merge(self::getAllUrl($this->user, 0),$v2);
        }
        $newResponse->getBody()->write(ResponseFormat::normalResp('获取成功',[
            'link'=>$link,
            'md5'=>md5(json_encode($link)),
            'rep'=>\App\Services\Config::get('flag_regex'),
            'default_rule' => $config['default_rule'] ?? 0,
        ]));
        return $newResponse;
    }

    public function downloadClash($request, $response)
    {
        $newResponse = $response->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $link = $this->downloadClashTest();

        if(strtolower(md5($link))==strtolower($_GET['md5'])){
            $data=[
              'needUpdate'=>0,
            ];
        }else{
            $data=[
                'needUpdate'=>1,
                'link'=>$link,
            ];
        }
        $newResponse->getBody()->write(ResponseFormat::normalResp('获取成功',$data));
        return $newResponse;
    }

    protected static function getAllUrl($user, $is_mu, $is_ss = 0)
    {
        $return_url = [];
        if (strtotime($user->expire_in) < time()) {
            return $return_url;
        }
        $items = array_merge(URL::getAllItems($user, 1, $is_ss), URL::getAllItems($user, 0, $is_ss));
        foreach ($items as $item) {
            $return_url []= URL::getItemUrl($item, $is_ss);
        }
        return $return_url;
    }

    public function userinfo($request, $response)
    {
        return $response->getBody()->write(ResponseFormat::normalResp('获取成功',$this->getUserInfo()));
    }


    public function getSubUrl($type)
    {
        $target=$type=='pc'?'subscribe':'android_subscribe';
        $base=self::getIniConfig($target);
        return str_replace(
            ['[subUrl]','[token]'],
            [Config::get('subUrl'),LinkController::GenerateSSRSubCode($this->user->id, 0)],
            $base
        );
    }

    public function getAndroidSubUrl()
    {
        $base=self::getIniConfig('android_subscribe');
        $items = array_filter(explode('|', $base));
        return array_map(function ($item) {
            return str_replace(
                ['[subUrl]','[token]'],
                [Config::get('subUrl'),LinkController::GenerateSSRSubCode($this->user->id, 0)],
                $item
            );
        }, $items);
    }

    protected function getUserInfo()
    {
        $data=[
            'username' => $this->user->user_name,
            'true_name'=>$this->user->email,
            'traffic' => [
                'total' => $this->user->transfer_enable,
                'used' => $this->user->u + $this->user->d,
            ],
            'last_checkin' => $this->user->lastCheckInTime(),
            'reg_date'=>$this->user->regDate(),
            'balance' => $this->user->money,
            'class'=>$this->user->class,
            'class_expire' => $this->user->class_expire,
            'node_speedlimit' => $this->user->node_speedlimit ?: '不限速',
            'node_connector' => $this->user->node_connector ?: '无限制',
            'pc_sub'=>$this->getSubUrl('pc'),
            'android_sub'=>$this->getAndroidSubUrl(),
            'defaultProxy'=>self::getIniConfig('subecribe_rule', 'Proxy')
        ];
//        var_dump($data);
        return $data;
    }


    public function init($request, $response)
    {
        $config_file = dirname(__DIR__) . '/config.ini';
        $config = parse_ini_file($config_file);
        if ($config['base_url']){
            $url = $config['base_url'];
        }else{
            $url = (strtolower($_SERVER['HTTPS']) == 'off' ? 'http' : 'https') . '://' . $_SERVER['HTTP_HOST'];
        }
        return $response->getBody()->write($url);
    }

    public function broadcast($request, $response)
    {
        $config_file = dirname(__DIR__) . '/config.ini';
        $config = parse_ini_file($config_file);
        $data= [
            'title' => $config['title'] ?? false,
            'content'=> $config['content'] ?? false,
            'broad_url'=> $config['broad_url'] ?? false,
            'broad_show'=> $config['broad_show'] ?? false,

            'bootstrap_show'=>$config['bootstrap_show'] ?? false,
            'bootstrap_img'=>$config['bootstrap_img'] ?? false,
            'bootstrap_url'=>$config['bootstrap_url'] ?? false,

            'version_code' => $config['update_version_code'] ?? 0,
            'description'=> $config['update_description'] ?? '',
            'download'=> $config['update_download'] ?? '',
        ];
        return $response->getBody()->write(ResponseFormat::normalResp('获取成功',$data));
    }

    public function update($request, $response){
        $config_file = dirname(__DIR__) . '/config.ini';
        $config = parse_ini_file($config_file);
        $data= [
            'version_code' => $config['update_version_code'] ?? 0,
            'description'=> $config['update_description'] ?? '',
            'download'=> $config['update_download'] ?? '',
        ];
        return $response->getBody()->write(ResponseFormat::normalResp('获取成功',$data));
    }

    public function logout($request, $response)
    {
        Auth::logout();
        return $response->getBody()->write(ResponseFormat::normalResp('登出成功'));
    }

    public function anno($request, $response)
    {
        $Anns = Ann::orderBy('date', 'desc')->select(['date','markdown'])->get();
        $data=$Anns?$Anns->toArray():[];
        return $response->getBody()->write(ResponseFormat::normalResp('获取成功',$data));
    }

    public function pcUpdateCheck($request, $response){
        $latest = self::getIniConfig('pc_update_version_code', 0);
        $curVersion=$request->getParam('curVersion')?:100;
        $data = [
            'update' => $latest > $curVersion,
        ];
        if ($latest>$curVersion){
            $data['desc'] = self::getIniConfig('pc_update_description');
            $data['pc'] = self::getIniConfig('pc_update_download');
            $data['mac'] = self::getIniConfig('pc_update_download_mac');
            $data['download'] = 'https://jianguo01.com/doc';
        }
        return ResponseFormat::normalResp('ok', $data);
    }

    public function pcAlert($request, $response){
        $show = self::getIniConfig('pc_anno_show', 0);
        $data=[
            'show'=>$show>0?true:false
        ];
        if ($show){
            $data['title']=self::getIniConfig('pc_anno_title', '');
            $data['content']=self::getIniConfig('pc_anno_content', '');
        }
        return ResponseFormat::normalResp('ok', $data);
    }

    public function downloadClashTest()
    {
        if(method_exists(URL::class,'getNew_AllItems')){
            $all=URL::getNew_AllItems($this->user,[
                'type'    => 'all',
                'emoji'   => false,
                'is_mu'   => 1
            ]);
        }else{
            $all=array_merge(
                self::addType(URL::getAllItems($this->user, 0, 1),'ss'),
                self::addType(URL::getAllItems($this->user, 1, 1),'ss'),
                self::addType(URL::getAllItems($this->user, 0, 0),'ssr'),
                self::addType(URL::getAllItems($this->user, 1, 0),'ssr')
            );
        }
        array_walk($all,function (&$item){
            $item = static::getClashURI($item);
        });
        $filter_data = array_filter($all);
        $proxy = array_map(function ($item) {
            return sprintf("  - %s", json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }, $filter_data);
        $proxy_name=array_map(function ($item){
            return sprintf("      - '%s'", $item);
        },array_column($filter_data,'name'));
        $result = file_get_contents(__DIR__ . '/clash.yaml');
        $result = str_replace(['__PROXY__', '__PROXY_NAME__'], [
                implode(PHP_EOL,$proxy),
                implode(PHP_EOL,$proxy_name),
        ], $result);
        return $result;
    }

    private static function addType($data,$type)
    {
        return array_map(function ($item) use ($type) {
            $item['type'] = $type;
            return $item;
        },$data);
    }


    private static function getClashURI($item)
    {
        $return = null;
        switch ($item['type']) {
            case 'ss':
                $return = [
                    'name' => $item['remark'],
                    'type' => 'ss',
                    'server' => $item['address'],
                    'port' => $item['port'],
                    'cipher' => $item['method'],
                    'password' => $item['passwd'],
                    'udp' => true
                ];
                if ($item['obfs'] != 'plain') {
                    switch ($item['obfs']) {
                        case 'simple_obfs_http':
                            $return['plugin'] = 'obfs';
                            $return['plugin-opts']['mode'] = 'http';
                            break;
                        case 'simple_obfs_tls':
                            $return['plugin'] = 'obfs';
                            $return['plugin-opts']['mode'] = 'tls';
                            break;
                        case 'v2ray':
                            $return['plugin'] = 'v2ray-plugin';
                            $return['plugin-opts']['mode'] = 'websocket';
                            if ($item['tls'] == 'tls') {
                                $return['plugin-opts']['tls'] = true;
                                if ($item['verify_cert'] == false) {
                                    $return['plugin-opts']['skip-cert-verify'] = true;
                                }
                            }
                            $return['plugin-opts']['host'] = $item['host'];
                            $return['plugin-opts']['path'] = $item['path'];
                            break;
                    }
                    if ($item['obfs'] != 'v2ray') {
                        if ($item['obfs_param'] != '') {
                            $return['plugin-opts']['host'] = $item['obfs_param'];
                        } else {
                            $return['plugin-opts']['host'] = 'windowsupdate.windows.com';
                        }
                    }
                }
                break;
            case 'ssr':
                $return = [
                    'name' => $item['remark'],
                    'type' => 'ssr',
                    'server' => $item['address'],
                    'port' => $item['port'],
                    'cipher' => $item['method'],
                    'password' => $item['passwd'],
                    'protocol' => $item['protocol'],
                    'protocolparam' => $item['protocol_param'],
                    'obfs' => $item['obfs'],
                    'obfsparam' => $item['obfs_param']
                ];
                break;
            case 'vmess':
                $return = [
                    'name' => $item['remark']??$item['ps'],
                    'type' => 'vmess',
                    'server' => $item['add'],
                    'port' => $item['port'],
                    'uuid' => $item['id'],
                    'alterId' => $item['aid'],
                    'cipher' => 'auto',
                    'udp' => true
                ];
                if ($item['net'] == 'ws') {
                    $return['network'] = 'ws';
                    $return['ws-path'] = $item['path'];
                    $return['ws-headers']['Host'] = ($item['host'] != '' ? $item['host'] : $item['add']);
                }
                if ($item['tls'] == 'tls') {
                    $return['tls'] = true;
                    if ($item['verify_cert'] == false) {
                        $return['skip-cert-verify'] = true;
                    }
                }
                break;
        }
        return $return;
    }

    public function online($request, $response)
    {
        $resp = require(__DIR__ . '/../preference.php');
        $callback = $resp['online']['callback'];
        return $response->getBody()->write(ResponseFormat::normalResp('获取成功',call_user_func($callback, $this->user)));
    }

    public function config()
    {
        $seg = $_GET['seg'] ?? null;
        $resp = require(__DIR__ . '/../preference.php');
        if ($seg && in_array($seg,['bootstrap','login','nav','levelDesc'])){
            $resp = $resp[$seg];
        }
        return json_encode($resp);
    }
}
