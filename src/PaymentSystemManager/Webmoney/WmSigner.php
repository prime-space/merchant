<?php namespace App\PaymentSystemManager\Webmoney;

use Exception;

class WmSigner
{
    private $wmid = '';
    private $ekey = '';
    private $nkey = '';

    public function __construct($wmid, $keyBase64, $pass)
    {
        $this->wmid = $wmid;
        $key = base64_decode($keyBase64);
        $key_data = unpack('vreserved/vsignflag/a16crc/Vlen/a*buf', $key);
        $key_test = $this->secureKeyByIDPW($wmid, $pass, $key_data);
        $sign_keys = $this->initKeys($key_test);
        $this->ekey = $this->hex2dec(bin2hex(strrev($sign_keys['ekey'])));
        $this->nkey = $this->hex2dec(bin2hex(strrev($sign_keys['nkey'])));
    }

    public function sign($data)
    {
        $plain = $this->md4($data);
        for ($i = 0; $i < 10; ++$i) {
            $plain .= pack('V', mt_rand());
        }
        $plain = pack('v', $this->strlen($plain)) . $plain;
        $m = $this->hex2dec(bin2hex(strrev($plain)));
        $a = $this->bcpowmod($m, $this->ekey, $this->nkey);
        $result = strtolower($this->shortunswap($this->dec2hex($a)));

        return $result;
    }

    private function strlen($data)
    {
        return mb_strlen($data, 'windows-1251');
    }

    private function md4($data)
    {
        return hash('md4', $data, true);
    }

    private function bcpowmod($m, $e, $n)
    {
        $r = '';
        while ($e != '0') {
            $t = bcmod($e, '4096');
            $r = substr('000000000000' . decbin(intval($t)), -12) . $r;
            $e = bcdiv($e, '4096', 0);
        }
        $r = preg_replace('!^0+!', '', $r);
        if ($r == '') {
            $r = '0';
        }
        $m = bcmod($m, $n);
        $erb = strrev($r);
        $result = '1';
        $a[0] = $m;
        for ($i = 1; $i < $this->strlen($erb); $i++) {
            $a[$i] = bcmod(bcmul($a[$i - 1], $a[$i - 1], 0), $n);
        }
        for ($i = 0; $i < $this->strlen($erb); $i++) {
            if ($erb[$i] == '1') {
                $result = bcmod(bcmul($result, $a[$i], 0), $n);
            }
        }

        return $result;
    }

    private function dec2hex($number)
    {
        $hexvalues = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');
        $hexval = '';
        while ($number != '0') {
            $hexval = $hexvalues[bcmod($number, '16')] . $hexval;
            $number = bcdiv($number, '16', 0);
        }
        if ($this->strlen($hexval) % 2) {
            $hexval = '0' . $hexval;
        }

        return $hexval;
    }

    private function hex2dec($number)
    {
        $decvalues = array(
            '0' => '0',
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4',
            '5' => '5',
            '6' => '6',
            '7' => '7',
            '8' => '8',
            '9' => '9',
            'A' => '10',
            'B' => '11',
            'C' => '12',
            'D' => '13',
            'E' => '14',
            'F' => '15'
        );
        $decval = '0';
        $number = strrev(strtoupper($number));
        for ($i = 0; $i < $this->strlen($number); $i++) {
            $decval = bcadd(bcmul(bcpow('16', $i, 0), $decvalues[$number[$i]], 0), $decval, 0);
        }

        return $decval;
    }

    private function shortunswap($hex_str)
    {
        $result = '';
        while ($this->strlen($hex_str) < 132) {
            $hex_str = '00' . $hex_str;
        }
        for ($i = 0; $i < $this->strlen($hex_str) / 4; $i++) {
            $result = substr($hex_str, $i * 4, 4) . $result;
        }

        return $result;
    }

    private function XOR($str, $xor_str, $shift = 0)
    {
        $str_len = $this->strlen($str);
        $xor_len = $this->strlen($xor_str);
        $i = $shift;
        $k = 0;
        while ($i < $str_len) {
            $str[$i] = chr(ord($str[$i]) ^ ord($xor_str[$k]));
            $i++;
            $k++;
            if ($k >= $xor_len) {
                $k = 0;
            }
        }

        return $str;
    }

    private function secureKeyByIDPW($wmid, $pass, $key_data)
    {
        $digest = $this->md4($wmid . $pass);
        $result = $key_data;
        $result['buf'] = $this->XOR($result['buf'], $digest, 6);

        return $result;
    }

    private function initKeys($key_data)
    {
        $crc_cont = '';
        $crc_cont .= pack('v', $key_data['reserved']);
        $crc_cont .= pack('v', 0);
        $crc_cont .= pack('V4', 0, 0, 0, 0);
        $crc_cont .= pack('V', $key_data['len']);
        $crc_cont .= $key_data['buf'];
        $digest = $this->md4($crc_cont);
        if (strcmp($digest, $key_data['crc'])) {
            throw new Exception('Checksum failed. KWM seems corrupted.');
        }

        $keys = unpack('Vreserved/ve_len', $key_data['buf']);
        $keys = unpack('Vreserved/ve_len/a' . $keys['e_len'] . 'ekey/vn_len', $key_data['buf']);
        $keys = unpack(
            'Vreserved/ve_len/a' . $keys['e_len'] . 'ekey/vn_len/a' . $keys['n_len'] . 'nkey',
            $key_data['buf']
        );

        return $keys;
    }
}
