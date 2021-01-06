<?php


namespace App\V1;


class Helper
{
    protected static function getKey()
    {
        return 'RocketMaker';
    }

    public static function rc4($pt,$key=null)
    {
        if (!$key){
            $key = self::getKey();
        }
        $s = array();
        for ($i=0; $i<256; $i++) {
            $s[$i] = $i;
        }

        $j = 0;
        $key_len = strlen($key);
        for ($i=0; $i<256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $key_len])) % 256;
            //swap
            $x = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $x;
        }
        $i = 0;
        $j = 0;
        $ct = '';
        $data_len = strlen($pt);
        for ($y=0; $y< $data_len; $y++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            //swap
            $x = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $x;
            $temp=$pt[$y] ^ chr($s[($s[$i] + $s[$j]) % 256]);
            $ct .= $temp;
        }
        return $ct;
    }

}
//
//$a = [
//    'http://gwgp-e7pfphrk7ts.n.bdcloudapi.com/v1/init',
//    'http://gwgp-xy6cj2jdpkj.n.bdcloudapi.com/v1/init',
//];
//echo base64_encode(Helper::rc4(json_encode($a),'IiiIas-;;asdp/'));
