<?php

namespace App\Utils;

use Log;
use App\Models\MemberDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GcashService
{
    protected $device;

    private $public_key = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAgu69jVUBQcbVHd+Gxtx7SoakvrHVi12kVYFO8+xEkR2txpE2JAUfLHA5/9oFjKComqaqxtNFMzBD1NwmE6YOUqlkizB8ntGE910jW8RWVpLsQYk6+Ym3SV/d9jrXVziTppY0vkEg0KP+TvwhSmrPcz8TWn1bAUCC9MqbMF/rprH4UijGwfZBJqlynNz2hIejvF0HzejIsSIGtsSdSAPNPJOenWvmooq0VudeFojt4xuSUGMGLzJo57MxiIiEqaeO8x9+k2t3i7ZjFH9tRvRNyHoa7k3lKR7v7krhlPPr5dhZoP+HQvSbPvmmbis9boRjr2fq0n2JaXsjrC222HwUBwIDAQAB';

    private $private_key = 'MIIEogIBAAKCAQEAgu69jVUBQcbVHd+Gxtx7SoakvrHVi12kVYFO8+xEkR2txpE2JAUfLHA5/9oFjKComqaqxtNFMzBD1NwmE6YOUqlkizB8ntGE910jW8RWVpLsQYk6+Ym3SV/d9jrXVziTppY0vkEg0KP+TvwhSmrPcz8TWn1bAUCC9MqbMF/rprH4UijGwfZBJqlynNz2hIejvF0HzejIsSIGtsSdSAPNPJOenWvmooq0VudeFojt4xuSUGMGLzJo57MxiIiEqaeO8x9+k2t3i7ZjFH9tRvRNyHoa7k3lKR7v7krhlPPr5dhZoP+HQvSbPvmmbis9boRjr2fq0n2JaXsjrC222HwUBwIDAQABAoIBACrkRKow55mBpj0EBaXNgoIWe4+QuDDQe04bbx7PDmMvgsbiuQaxutWW6hkbdefslW8cHCPIAApVzFLFz59uLZD8ttg2CQ0J+/IAy917AwGXXdfWOcCXUbiytAa+nd6PxSa0XBDbSwGuR1felpVHSjApwJBLMm3FkWDZol8FfS/87gCSTvoZXatXB5QX8uV/rStzAqsXT4oRc7IW5nl/wiG3NQxfPHkqmdfH+VBh/Q5TKY8F90q5LuoZnFxYfO7FvQwSzKi2G2D4PUjDxq5+9WoAEC/PF93mHQNmUHJSQiA6Cg6uvpdzyVlnMt5zWGg3Hu75BdjTXJsKyG5FjOfIh6kCgYEA9Y3HnlYRxIoksZ0twZNyQ5EtKbpY41DjBCAvU0gdQZmNdV/fmmGiTxTGV9Mu+YQMPEKOZXjFHYocPbAiGDGt+eNmJIi/TcZU64EpDF3Z9avl4SEvEE2D0rvlIGf7v2Yiy+1x81YT7KKf+zdyWhWa5EAc56koRYfOWjGbFu8FQWsCgYEAiICrniflligPDSgvPJqH8nDkvmqh9B8j6MchmyB8jlBozPbbsNpb6rJ4ApkeSu/jZZ/xbs0+fxC+ecQkihLBK9IpT5oZZj/ez5mGfL0C+VCiXZlgzMLsUZXmmNo7K59jxjCCPyFT3Cinyu+s6vvowbhXS+B5UZbq7h8B3PWxctUCgYApBCS64v+Wx8h3Tpzs/7cPaDmWBcWBOuqgrsuY6qvQYFjLqVcNT4+wC/VRiDoJfbAZhLiKZJDwbThoaXpYdjvsSLqwEZN6500aBXoY0bhtm+gLIeLdo0UIt0//iL75apMXYzMshU1Qsp1sdgeI2qEDzV3GqG/MpXGniS+xtf03vwKBgHW1BshFvRtjhb8htMH8u1gbc5SjnO5G4A89j8QWGnIZ8pU0FbOGSBa1OPl8kfuqqpsadfXG9KpbhPS5Z4zMqVihTFBBIL/kOb0otSjhUCwvFSPDPA6101Ry/7s1DCsMsdvYPqgzk/3X7QD49lJXUZmi3VwnwFXT3tfhUxj3oMHVAoGAVl1IYK8YDg5l//3qLcKxZZrV+IbgMD8XYWRo/1PacKAH/WlZLUPljRi/o7ftPvrWGoOOydY5fRnP2DhPllEpooech+IB1vhoN1cDsFOrJFqlBZLZTA9sTFnBG2LQr/QD8JEOGQAswkiwBcHasRX2Xwxhpt5+eRME+wODgVBW45Y=';

    private $rds_data = '{"version":"2","data":"9863093f86040e040e040e043c6908b4c66ccfc436c126c37d663f8bf5a63be2b719c469d705f0e9a76940602ee3b3141be2ca5c3f5411ab4af5bd103bf1dae018f76d21684d7a8822412a0f466b0bc6cfda533062d9076e09d9b0b9f412c74e0acaa6d250523830051d2eb7be2985d2270c04e31c9908595a34bcff0770744577af082563683bbbeea0f171a4f61092a5e23ea34fc9fb5a2019ea402e7c72d5aa502752ec6efc4f95ff2c9795e554c3502a9706a3f54aed9a510e80501b958c6b5709a1067446e08f3155b5cffdd0aaab3639261fa810f9e414baf2b81084034bd452a2ae92e8a26e58b7c9d8b5e5a87a8065d103f69810b80d22a2f7b06e4e17891be08443f91b817834a6c179a39c7cb337ec7835e760f4d6c00b15ebcf69124992b60371c45b62a8a301bec2e81c8e0cb7eb94878f23a18977d38c7b24306e0390515b19cb935f385814e38a929aa7d7220f99e80e0c88c640d4fedcd7955c1bbbb53cca68499f1b00029c719c9784a6c781c7f07b51714e803066e8cd6d77c46a48c8b56647c514ed2b6f284a465952e5decbbec3dca2acedf64e6e2b8240f1eba1834acb1ed9bcbce1c39d971871855d80c2262dd6c876f28515094698c395ecdb0a4ef5403ae1cd0f06c5b9e85ab81bc12fa54d575fd1e52e292048ac4a2a88c959ea8d870928039e5b8f50e147ebdf9df879095c3dd2d145bcaeeedafba579be270618df111ad3cf555d54d1b0388c0c9c1dff3ba5226f5b4d10f1f8b59bd627b8ee6272982d1fede15cc6169cf8791498f076ee609e9f6661354db05c5ea62dc6a9ee6c72a6073bfe4593697d341578df834e85424888aaf21e6d91f9f7f1d62a12556bbb0c73f3e838bad2f949fe4e0914ce2a27d10a51f6593d60dcd0c16d9295c3b33b05f1f7a603746e879665cf4c8d3ad9ece51ed342b83e86d74dbb954e844593680c40d26517da867410664d708d00c1169de313494569e21a2446c81f55e30e785febe77b4f987bc9397c8a883ead567c41b428e9c013a358eee1cea831c751264c09f2f073ba87915ae07fe37522292840d8f28ccca5a40c049bbe60bfd7d0c43b4730b8f866cf9a1ab0e57f2c5853625c8e74e43ee026537c58d31871b48cacc8d3c2d1f908e44255aa8780d4b4bcabefc7622df9f3c2ca394053daaacb911c1d71796e83f07957b10661b3416693715c37d4ca3bc88f5783bf68d636c31a7a3b79c9ad5ba56bfd861a2c206c952825082fee00158de615d1692498f83924b1804470f629486a7a9341ec40a1c88863f1a9661c5c44da7f8c0140b64804b1dce9b46695033da0c4a0debffece91271bd31d5a41ec9bae3b7e85eefd21d1408215417c3ad079324fe067c15898fda1b0654e63ac4a53f6fca08ccc36c8a36a90f09c26adf7db9d537ed43cddc9390717f68610c47185e555699dc05adeb5dfbcc32c1e2cdd13f304e56604e588f23a1f69162483d6ad680c6ee1efeba96a58cfda60d8b204ef4cae3c4e376f9e56b1be3d2fea14dc0a36511a2fce0e667bea1e9a29406fafb40f7ac066f1e301d75260003847e8056fc7e32bf3479908dd135ae66f16e6f871a855b172d6bd9fe94405d6875132d804e44703a79785c4fcbfc13a0ec6627495c6f46dcd8838e8f5398331c1c266572650a37a20f24e82b0e038d376f122c1290c9d8ede21c05b5985325e1f965d057c6a67fbee6662c143a162bf7c3fccabc55def66c2ff16d1bef0fabe0512e2130ae654ecc3f2621b5604032e80dd5e0c5f07172684fd698d1944e7d45c13f4acf071136401d4483f5d555e01db09ea0fe3f45fa0840f6bf8c9cf3a311029d5ab2d8668d6b3a6897bfc3aff07b41b44adc497e1f530c2bf15471e3883323832b7ffa2962dd3488547eb0249fd331b1959276b76"}';

    private $aesKey = 'lKI3wQ$o!vr~IoUk~0PzgSaR+S7h8s{Y';
    private $aesIV = '<^48GZFpDY_[W^PF';
    private $dfp_token = 'QN6lBcSjRjUMDXFDkW99h/DysefPhS2tOXbYsG6eg3Wpxc6OgQEAAA==';
    private $PackageID = 'sUtRRcZqw9gBGtUxlHxjtmYN/Hw=';

    private $headers = [
        'Content-Type: application/json',
        'User-Agent: okhttp/3.12.12',
    ];

    private $gcash_url = [
        'handshack' => 'https://api.mynt.xyz/c4/v3/key-agreement/handshake',
        'login' => 'https://api.mynt.xyz/c4/v2.3/otp/generate_code',
        'otp_submit' => 'https://api.mynt.xyz/c4/v2.3/otp/verify_code',
        'mpin_login' => 'https://api.mynt.xyz/c4/v2/mpin/login',
        'getDetails' => 'https://api.mynt.xyz/c4/v2/userinfo/details',
        'rba' => 'https://api.mynt.xyz/c4/v2/transfer/rba',
        'rba_send' => 'https://mgs-gw.paas.mynt.xyz/mgw.htm',
        'getHistory' => 'https://api.mynt.xyz/c4/v2.1/transactions',
        'getBalance' => 'https://api.mynt.xyz/c4/v2/balance',
        'send_to_bank' => 'https://access.mynt.xyz/mac/gcash/xapi/1.4/instapay/deposit',
        'user_info' => 'https://api.mynt.xyz/c4/v2/userinfo/details/lite',
        'change_mpin' => 'https://api.mynt.xyz/c4/v2.2/changepin',
    ];

    private static function GeraHash($qtd){
        $Caracteres = 'ABCDEFGHIJKLMOPQRSTUVXWYZabcdefghijklmnopqrstuvwxyz0123456789';
        $QuantidadeCaracteres = strlen($Caracteres);
        $QuantidadeCaracteres--;

        $Hash=NULL;
        for($x=1;$x<=$qtd;$x++){
            $Posicao = rand(0,$QuantidadeCaracteres);
            $Hash .= substr($Caracteres,$Posicao,1);
        }

        return $Hash;
    }
    private static function getSign($content, $privateKey){
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($privateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        $key = openssl_get_privatekey($privateKey);
        openssl_sign($content, $signature, $key, "SHA256");
        // openssl_free_key($key);
        $sign = base64_encode($signature);
        return $sign;
    }
    public function makeDevice($phone)
    {
        $data = [
            'udid' => 'AND'.$this->GeraHash(29),
            'mobileNum' => $phone,
            'client_publicKey' => $this->public_key,
            'client_privateKey' => $this->private_key,
            'server_publicKey' => '',
            'aesKey' => $this->aesKey,
            'aesIV' => $this->aesIV,
            'refid' => $this->GeraHash(32),
            'X_DFP_TOKEN' => $this->GeraHash(21).'/'.$this->GeraHash(32).'==',
            'X_PHONE_BRAND' => 'samsung',
            'X_PHONE_MANUFACTURER' => 'samsung',
            'X_PHONE_MODEL' => 'SM-G970N',
        ];

        $device = MemberDevice::firstOrCreate(['device' => $phone], ['data' => $data]);

        return $device;
    }

    public function handshake($model)
    {
        // $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"SM-G970N","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'","scenario_id":"first_time_otp"}';
        $data = [
            'pub' => $model->client_publicKey,
            'udid' => $model->udid,
        ];
        $res = json_decode($this->curl($this->gcash_url['handshack'],json_encode($data),$this->headers),true);
        $model->server_publicKey = $res['pub'];
        $model->flowId = $res['flowId'];

        MemberDevice::where('device', $model->mobileNum)->update(['data' => json_decode(json_encode($model), true)]);

        return $model;
    }

    public function makeOTP($model)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'","scenario_id":"first_time_otp"}';

        $data = [
            'request' => [
                'body' => [
                    'msisdn' => $model->mobileNum,
                    'udid' => $model->udid,
                ],
                'method' => 'POST',
                'header' => [
                    'X-Env-Info' => base64_encode($X_Info),
                    'X-Package-Id' => $this->PackageID,
                    'X-UDID' => $model->udid,
                    'X-FlowId' => $model->flowId,
                    'Authorization' => ''
                ],
            ],
            'sec' => [
                'key' => '',
                'initializer' => '',
                'enc' => [
                ],
            ],
        ];

        $post_data = base64_encode(json_encode($data));
        $post_json['aesKey'] = $this->aesKey;
        $post_json['iv'] = $this->aesIV;
        $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

        $res = json_decode($this->curl($this->gcash_url['login'],json_encode($post_json),$this->headers),true);
        // Log::debug('SendLoginSMS_Response:'.json_encode($res));
        Log::debug(__METHOD__, compact('res'));
        if(isset($res['response']['body']['code'])) {
            if ($res['response']['body']['code'] == 0){
                return ['status' => true,'message' => '簡訊發送成功'];
            }
            if ($res['response']['body']['code'] == 2){
                return ['status' => false, 'message' => "Please wait for 5 minutes and try again."];
            }
        }
        return ['status' => false,'message' => '失敗'];
    }

    public function checkOTP($model,$otp)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'","scenario_id":"first_time_otp"}';

        $data = [
            'request' => [
                'body' => [
                    'msisdn' => $model->mobileNum,
                    'udid' => $model->udid,
                    'code' => $otp,
                ],
                'method' => 'POST',
                'header' => [
                    'X-Env-Info' => base64_encode($X_Info),
                    'X-Package-Id' => $this->PackageID,
                    'X-UDID' => $model->udid,
                    'X-FlowId' => $model->flowId,
                    'Authorization' => ''
                ],
            ],
            'sec' => [
                'key' => '',
                'initializer' => '',
                'enc' => [
                ],
            ],
        ];

        $post_data = base64_encode(json_encode($data));
        $post_json['aesKey'] = $this->aesKey;
        $post_json['iv'] = $this->aesIV;
        $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

        $res = json_decode($this->curl($this->gcash_url['otp_submit'],json_encode($post_json),$this->headers),true);
        Log::debug(__METHOD__, compact('res'));
        if(isset($res['response']['body']['code']) && $res['response']['body']['code'] == 0){
            return ['status' => true,'message' => '認證成功'];
        }
        return ['status' => false,'message' => $res];
    }

    public function mpinLogin($model,$mpin)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'"}';

        $data = [
            'request' => [
                'body' => [
                    'msisdn' => $model->mobileNum,
                    'mpin' => $mpin,
                    'enc' => [
                    ],
                    'rds_data' => $this->rds_data,
                ],
                'method' => 'POST',
                'header' => [
                    'X-Env-Info' => base64_encode($X_Info),
                    'X-Package-Id' => $this->PackageID,
                    'X-UDID' => $model->udid,
                    'X-FlowId' => $model->flowId,
                    'Authorization' => ''
                ],
            ],
            'sec' => [
                'key' => '',
                'initializer' => '',
                'enc' => [
                ],
            ],
        ];

        $post_data = base64_encode(json_encode($data));
        $post_json['aesKey'] = $this->aesKey;
        $post_json['iv'] = $this->aesIV;
        $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

        $res = json_decode($this->curl($this->gcash_url['mpin_login'],json_encode($post_json),$this->headers),true);
        Log::debug(__METHOD__, compact('res'));
        if(isset($res['response']['body']['result_code']) && $res['response']['body']['result_code'] == '00000000'){
            $model->access_token = $res['response']['body']['access_token'];

            MemberDevice::where('device', $model->mobileNum)->update(['data' => json_decode(json_encode($model), true)]);

            return ['status' => 1,'message' => 'mpin登入成功'];
        }elseif(isset($res['code']) && $res['code'] == 143){
            return ['status' => 3,'message' => '登入失敗'];
        }elseif(isset($res['response']['body']['message']) && Str::contains($res['response']['body']['message'], 'try removing this app')) {
            return ['status' => 4,'message' => '設備失敗'];
        }
        return ['status' => 2,'message' => $res];
    }

    public function getDetails($model)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'"}';

        $data = [
            'request' => [
                'body' => [
                    'parameter' => '',
                ],
                'method' => 'GET',
                'header' => [
                    'X-Env-Info' => base64_encode($X_Info),
                    'X-Package-Id' => $this->PackageID,
                    'X-UDID' => $model->udid,
                    'X-FlowId' => $model->flowId,
                    'Authorization' => $model->access_token
                ],
            ],
            'sec' => [
                'key' => '',
                'initializer' => '',
                'enc' => [
                ],
            ],
        ];

        $post_data = base64_encode(json_encode($data));
        $post_json['aesKey'] = $this->aesKey;
        $post_json['iv'] = $this->aesIV;
        $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

        $res = json_decode($this->curl($this->gcash_url['getDetails'],json_encode($post_json),$this->headers),true);
        Log::debug(__METHOD__, compact('res'));
        if(isset($res['success']) && $res['success'] == true){
            $model->user_id = $res['data']['user_id'];
            $model->detail = json_encode($res, true);
            MemberDevice::where('device', $model->mobileNum)->update(['data' => json_decode(json_encode($model), true)]);
            return ['status' => true,'message' => '取得資料成功', 'res' => $res];
        }
        return ['status' => false,'message' => '取得資料失敗', 'res' => $res];
    }

    public function getUserInfo($model,$mobile)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'"}';

        $data = [
            'request' => [
                'body' => [
                    'targetMsisdn' => $mobile,
                ],
                'method' => 'POST',
                'header' => [
                    'X-Env-Info' => base64_encode($X_Info),
                    'X-Package-Id' => $this->PackageID,
                    'X-UDID' => $model->udid,
                    'X-FlowId' => $model->flowId,
                    'Authorization' => $model->access_token
                ],
            ],
            'sec' => [
                'key' => '',
                'initializer' => '',
                'enc' => [
                ],
            ],
        ];

        $post_data = base64_encode(json_encode($data));
        $post_json['aesKey'] = $this->aesKey;
        $post_json['iv'] = $this->aesIV;
        $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

        $res = json_decode($this->curl($this->gcash_url['user_info'],json_encode($post_json),$this->headers),true);
        Log::debug(__METHOD__, compact('res'));
        if($res['success'] == true){
            return ['status' => true,'message' => '取得資料成功','info' => $res['data']];
        }
        return ['status' => false,'message' => '取得資料失敗'];
    }

    public function getLimit($model)
    {
        // $requestData = '[{"action":"VIEW","data":"{\"userId\":\"'.$model->user_id.'\"}","envData":"{\"appVersion\":\"2.3\",\"sdkVersion\":\"1.0.0\",\"deviceType\":\"android\",\"clientKey\":\"\",\"apdid\":\"eYOIkuecdGPcCenW0b285oVCUIsHqGom0DE5ALJQgwLcVgpf17biITLh\",\"osVersion\":\"5.1.1\",\"deviceModel\":\"'.$model->X_PHONE_MODEL.'\",\"language\":\"en_US\",\"umidToken\":null,\"apdidToken\":\"figOeTNfxY1pc0AI4wPqpgjQzcTEHxdjbS1ccsZUOr7Q0j3LgwEAAA==\"}","isDisplaySensitiveField":false,"module":"INIT","verifyId":"'.$model->securityId.'"}]';
        $requestData = '[{"envInfo":{"appVersion":"5.57.1: 707","channel":"GCASH_APP","deviceId":"YsKKFauSFyUDALbefk7N1RwD","extendInfo":{"appVersion":"5.57.1: 707","dfpToken":"'.$model->X_DFP_TOKEN.'","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","phoneManufacturer":"samsung","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneOsVersion":"5.1.1,22","udid":"'.$model->udid.'","userAgent":"GCash App Android"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","thirdChannel":0,"tokenId":"'.$model->X_DFP_TOKEN.'"},"userId":"'.$model->user_id.'"}]';

        $timestamp = $this->getUnixTimestamp();
        $headers = [
            'Host: mgs-gw.paas.mynt.xyz',
            'User-Agent: GCash/5.57.1: 707 Language/zh_TW',
            'tenantId: MYNTPH',
            'uuid: '.$this->guid(),
            'did: YsKKFauSFyUDALbefk7N1RwD',
            'workspaceId: PROD',
            'AppId: D54528A131559',
            'sessionId: '.$model->access_token,
            // 'Accept-Encoding: gzip',
            'ts: '.$timestamp,
            'clientId: 460007525224200|010067021561177',
            'sessionType: GCASH',
            'version: 1.0',
            'Accept-Language: zh-Hant',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
        ];
        $data = [
            'id' => 14,
            'operationType' => 'ap.mobilewallet.profile.limit.check',
            'requestData' => $requestData,
            'ts' => $timestamp,
        ];
        $data['sign'] = md5('b42bf7929d836ead7b9658e6ca18ae40&'.urldecode(http_build_query($data)));

        $res = json_decode($this->curl($this->gcash_url['rba_send'],http_build_query($data),$headers),true);
        Log::debug(__METHOD__, compact('res'));
        return $res;
    }

    public function getBalance($model)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'"}';

        $data = [
            'request' => [
                'body' => [
                    'parameter' => '',
                ],
                'method' => 'GET',
                'header' => [
                    'X-Env-Info' => base64_encode($X_Info),
                    'X-UDID' => $model->udid,
                    'X-FlowId' => $model->flowId,
                    'Authorization' => $model->access_token,
                    "X-Correlator-Id" => "iDzFwU6KYKPjNZ5TqNW5fAkPFc3V5gdY",
                ],
            ],
            'sec' => [
                'key' => '',
                'initializer' => '',
                'enc' => [
                ],
            ],
        ];

        $post_data = base64_encode(json_encode($data));
        $post_json['aesKey'] = $this->aesKey;
        $post_json['iv'] = $this->aesIV;
        $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

        $res = json_decode($this->curl($this->gcash_url['getBalance'],json_encode($post_json),$this->headers),true);
        Log::debug(__METHOD__, compact('res'));
        if(isset($res) && $res['success'] == true){
            return ['status' => true,'message' => '取得餘額成功','detail' => $res];
        }
        return ['status' => false,'message' => '取得餘額失敗'];
    }

    public function getHistory($model)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'"}';

        $data = [
            'request' => [
                'body' => [
                    'parameter' => '',
                ],
                'method' => 'GET',
                'header' => [
                    'X-Env-Info' => base64_encode($X_Info),
                    'X-Package-Id' => $this->PackageID,
                    'X-UDID' => $model->udid,
                    'X-FlowId' => $model->flowId,
                    'Authorization' => $model->access_token
                ],
            ],
            'sec' => [
                'key' => '',
                'initializer' => '',
                'enc' => [
                ],
            ],
        ];

        $post_data = base64_encode(json_encode($data));
        $post_json['aesKey'] = $this->aesKey;
        $post_json['iv'] = $this->aesIV;
        $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

        $res = json_decode($this->curl($this->gcash_url['getHistory'],json_encode($post_json),$this->headers),true);
        Log::debug(__METHOD__, compact('res'));
        return $res;
    }
    public function pay_to_bank($model,$payto,$amount,$bank_info)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'"}';
        $headers = [
            'Authorization: '.$model->access_token,
            'X-UDID: '.$model->udid,
            'X-Gateway-Auth: YW5kcm9pZDphbmRyb2lk',
            'X-Env-Info:'.base64_encode($X_Info),
            'x-auth:'.'APIAuth '.$model->udid.':'.'fSrls2Zd4e99yIvwTGxNANFBhRJSUSCYU7ZeMdC3RgE=',
            // 'X-Signature: Sb3TeDBljDIWA6bEwf6n2TIy08GiNmrPt7WqaVueJ4YYfdDONstwMav7V2XO+jAm+x2niW7aYcjm0/7MQAZLv+wL/tdg5BVN46hmK+1jMMPOPlHzQxdE9suymvrElPXmEWPhFNZpfr5O2lFRfXfBs0wFQBUUN82szktslxfcJx0u8gg73Ny9CxXB0rn+1la005pcfvUVbwQmGGZyH7qCMG/S5dD6VFLDzZkllRKWL/l/vHxBiYPazVP1XFrN9vsIYOID9/pMJ8Q0RXEylk0X5PF0mgxoNOxlODUhhP20YO7EzHUJysIVb/JOJfOwF2gYm2DY8HLz5tGowCZ/tYIKdQ==',
            'Content-Type: application/json',
            'Accept-Encoding: gzip',
            'User-Agent: okhttp/3.12.12',
        ];
        //{"txnamt":10.0,"tfrname":"157502001074","tfracctno":"157502001074","param1":"baboysiame@gmail.com","acctident":"1991-10-23/Manila","tfrbnkname":"China Banking Corporation","tfrbnkcode":"0010","acctname":"REBECCA NICOLATA ARPON","acctno":"217010000137927556725","msisdn":"09568710676"}
        $detail = json_decode($model->detail,true);
        preg_match('/.* (?<country>.*)/',$detail['data']['birth_place'],$country);

        if(!isset($country['country'])){
            $country['country'] = $detail['data']['birth_place'];
        }

        $data = [
            'txnamt' => (float)$amount,
            'tfrname'=>$payto,
            'tfracctno'=>$payto,
            'param1' => '',
            'acctident' => $detail['data']['birthday'].'/'.$country['country'],
            'tfrbnkname' => $bank_info['bank_name'],
            'tfrbnkcode' => $bank_info['bank_code'],
            'acctname' => $detail['data']['first_name'].' '.$detail['data']['middle_name'].' '.$detail['data']['last_name'],
            'acctno' => $detail['data']['user_id'],
            'msisdn' => $detail['data']['mobile_number'],
        ];

        $res = json_decode($this->curl($this->gcash_url['send_to_bank'],json_encode($data),$headers),true);
        Log::debug(__METHOD__, compact('res', 'headers', 'data'));
        // if(isset($res['message']) && preg_match('/An error occurred during the request/',$res['message'])){
        //     $res = json_decode($this->curl($this->gcash_url['rba'],json_encode($post_json),$this->headers),true);
        //     Log::debug('retry:'.json_encode($res));
        // }
        if(isset($res['gcash_trans_id'])){
            return ['status' => 1,'message' => '出款成功','transId' => $res['gcash_trans_id']];
        }else if(isset($res['riskResult']) && $res['riskResult'] == 'VERIFICATION'){
            $model->securityId = $res['verificationId'];
            MemberDevice::where('device', $model->mobileNum)->update(['data' => json_decode(json_encode($model), true)]);
            return ['status' => 2,'message' => '簡訊驗證'];
        }
        return ['status' => -1,'message' => $res];
    }
    //發送出款簡訊
    public function pay2_to_bank($model)
    {
        $requestData = '[{"action":"VIEW","data":"{\"userId\":\"'.$model->user_id.'\"}","envData":"{\"appVersion\":\"2.3\",\"sdkVersion\":\"1.0.0\",\"deviceType\":\"android\",\"clientKey\":\"\",\"apdid\":\"eYOIkuecdGPcCenW0b285oVCUIsHqGom0DE5ALJQgwLcVgpf17biITLh\",\"osVersion\":\"5.1.1\",\"deviceModel\":\"'.$model->X_PHONE_MODEL.'\",\"language\":\"en_US\",\"umidToken\":null,\"apdidToken\":\"figOeTNfxY1pc0AI4wPqpgjQzcTEHxdjbS1ccsZUOr7Q0j3LgwEAAA==\"}","isDisplaySensitiveField":false,"module":"INIT","verifyId":"'.$model->securityId.'"}]';

        $timestamp = $this->getUnixTimestamp();
        $headers = [
            'User-Agent: GCash/5.57.1: 707 Language/zh_TW',
            'tenantId: MYNTPH',
            'uuid: '.$this->guid(),
            'did: YsKKFauSFyUDALbefk7N1RwD',
            'workspaceId: PROD',
            'AppId: D54528A131559',
            'sessionId: '.$model->access_token,
            'Accept-Encoding: gzip',
            'ts: '.$timestamp,
            'clientId: 460007525224200|010067021561177',
            'sessionType: GCASH',
            'version: 1.0',
            'Accept-Language: zh-Hant',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
        ];
        $data = [
            'id' => 1,
            'operationType' => 'com.alipay.fc.riskcloud.dispatch',
            'requestData' => $requestData,
            'ts' => $timestamp,
        ];
        $data['sign'] = md5('b42bf7929d836ead7b9658e6ca18ae40&'.urldecode(http_build_query($data)));

        $res = json_decode($this->curl($this->gcash_url['rba_send'],http_build_query($data),$headers),true);
        Log::debug(__METHOD__, compact('res'));
        return $res;
    }

    public function pay3_to_bank($model,$otp,$payto,$amount,$bank_info)
    {
        $requestData = '[{"action":"VERIFY","data":"{\"data\":\"'.$otp.'\"}","envData":"{\"appVersion\":\"2.3\",\"sdkVersion\":\"1.0.0\",\"deviceType\":\"android\",\"clientKey\":\"\",\"apdid\":\"eYOIkuecdGPcCenW0b285oVCUIsHqGom0DE5ALJQgwLcVgpf17biITLh\",\"osVersion\":\"5.1.1\",\"deviceModel\":\"'.$model->X_PHONE_MODEL.'\",\"language\":\"en_US\",\"umidToken\":null,\"apdidToken\":\"figOeTNfxY1pc0AI4wPqpgjQzcTEHxdjbS1ccsZUOr7Q0j3LgwEAAA==\"}","isDisplaySensitiveField":false,"module":"otpSms","verifyId":"'.$model->securityId.'","version":"3.1.8.100"}]';

        $timestamp = $this->getUnixTimestamp();
        $headers = [
            'User-Agent: GCash/5.57.1: 707 Language/zh_TW',
            'tenantId: MYNTPH',
            'uuid: '.$this->guid(),
            'did: YsKKFauSFyUDALbefk7N1RwD',
            'workspaceId: PROD',
            'AppId: D54528A131559',
            'sessionId: '.$model->access_token,
            'Accept-Encoding: gzip',
            'ts: '.$timestamp,
            'clientId: 460007525224200|010067021561177',
            'sessionType: GCASH',
            'version: 1.0',
            'Accept-Language: zh-Hant',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
        ];
        $data = [
            'id' => 2,
            'operationType' => 'com.alipay.fc.riskcloud.dispatch',
            'requestData' => $requestData,
            'ts' => $timestamp,
        ];
        $data['sign'] = md5('b42bf7929d836ead7b9658e6ca18ae40&'.urldecode(http_build_query($data)));
        // $test = $this->getDetails($model);
        // if($test['status'] == false){
        //     return ['status'=>3,'message'=> 'Don\'t Login on your phone']; //出款失敗
        // }
        $res = json_decode($this->curl($this->gcash_url['rba_send'],http_build_query($data),$headers),true);

        if(isset($res['result']) && $res['result']['verifyCode'] == 'SUCCESS'){
            //審核出款
            $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'"}';
            $headers = [
                'Authorization: '.$model->access_token,
                'X-UDID: '.$model->udid,
                'X-Gateway-Auth: YW5kcm9pZDphbmRyb2lk',
                'X-Env-Info:'.base64_encode($X_Info),
                'x-auth:'.'APIAuth '.$model->udid.':'.'fSrls2Zd4e99yIvwTGxNANFBhRJSUSCYU7ZeMdC3RgE=',
                // 'X-Signature: Sb3TeDBljDIWA6bEwf6n2TIy08GiNmrPt7WqaVueJ4YYfdDONstwMav7V2XO+jAm+x2niW7aYcjm0/7MQAZLv+wL/tdg5BVN46hmK+1jMMPOPlHzQxdE9suymvrElPXmEWPhFNZpfr5O2lFRfXfBs0wFQBUUN82szktslxfcJx0u8gg73Ny9CxXB0rn+1la005pcfvUVbwQmGGZyH7qCMG/S5dD6VFLDzZkllRKWL/l/vHxBiYPazVP1XFrN9vsIYOID9/pMJ8Q0RXEylk0X5PF0mgxoNOxlODUhhP20YO7EzHUJysIVb/JOJfOwF2gYm2DY8HLz5tGowCZ/tYIKdQ==',
                'Content-Type: application/json',
                'Accept-Encoding: gzip',
                'User-Agent: okhttp/3.12.12',
            ];

            $detail = json_decode($model->detail,true);
            preg_match('/.* (?<country>.*)/',$detail['data']['birth_place'],$country);

            if(!isset($country['country'])){
                $country['country'] = $detail['data']['birth_place'];
            }

            $data = [
                'txnamt' => (float)$amount,
                'tfrname'=>$payto,
                'tfracctno'=>$payto,
                'param1' => '',
                'acctident' => $detail['data']['birthday'].'/'.$country['country'],
                'tfrbnkname' => $bank_info['bank_name'],
                'tfrbnkcode' => $bank_info['bank_code'],
                'acctname' => $detail['data']['first_name'].' '.$detail['data']['middle_name'].' '.$detail['data']['last_name'],
                'acctno' => $detail['data']['user_id'],
                'msisdn' => $detail['data']['mobile_number'],
                'securityid' => $res['result']['verifyId'],
            ];

            $test = $this->getDetails($model);
            if($test['status'] == false){
                return ['status'=>3,'message'=> 'Don\'t Login on your phone']; //出款失敗
            }
            $res = json_decode($this->curl($this->gcash_url['send_to_bank'],json_encode($data),$headers),true);
            Log::debug(__METHOD__, compact('res'));
            //An error occurred during the request 重新發送一次
            // if(isset($res['message']) && preg_match('/An error occurred during the request/',$res['message'])){
            //     $res = json_decode($this->curl($this->gcash_url['send_to_bank'],json_encode($data),$headers),true);
            //     Log::debug('retry:'.json_encode($res));
            // }
            if(isset($res['message']) && $res['message'] == 'Success'){
                $model->transId = $res['gcash_trans_id'];
                MemberDevice::where('device', $model->mobileNum)->update(['data' => json_decode(json_encode($model), true)]);
                return ['status'=>1,'message'=>'出款成功'];
            }else{
                if(isset($res['message'])){
                    $return = $res['message'];
                }else{
                    $return = $res;
                }
                return ['status'=>3,'message'=>$return]; //出款失敗
            }
        }
        if(!isset($res['result']['verifyMessage'])){
            return ['status'=>3,'message'=> 'The payment was error,please resubmit']; //出款失敗
        }
        return ['status'=>2,'message'=>$res['result']['verifyMessage']];
    }


    public function pay($model,$payto,$amount)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'"}';

        $data = [
            'request' => [
                'body' => [
                    'target' => $payto,
                    'amount' => $amount,
                    'message' => '',
                ],
                'method' => 'POST',
                'header' => [
                    'X-Env-Info' => base64_encode($X_Info),
                    'X-Package-Id' => $this->PackageID,
                    'X-UDID' => $model->udid,
                    'X-FlowId' => $model->flowId,
                    'Authorization' => $model->access_token,
                    "X-Correlator-Id" => "iDzFwU6KYKPjNZ5TqNW5fAkPFc3V5gdY",
                ],
            ],
            'sec' => [
                'key' => '',
                'initializer' => '',
                'enc' => [
                ],
            ],
        ];

        $post_data = base64_encode(json_encode($data));
        $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

        $res = json_decode($this->curl($this->gcash_url['rba'],json_encode($post_json),$this->headers),true);
        Log::debug('GcashServcie pay response', compact('res'));
        //An error occurred during the request 重新發送一次
        if(isset($res['response']['body']['message']) && preg_match('/An error occurred during the request/',$res['response']['body']['message'])){
            $res = json_decode($this->curl($this->gcash_url['rba'],json_encode($post_json),$this->headers),true);
            Log::debug('GcashServcie pay retry:', compact('res'));
        }
        if(isset($res['response']['body']['riskChallengeView']['riskResult']) && $res['response']['body']['riskChallengeView']['riskResult'] == 'ACCEPT'){
            return ['status' => 1,'message' => '出款成功','transId' => $res['response']['body']['transId']];
        }else if(isset($res['response']['body']['riskChallengeView']['riskResult']) && $res['response']['body']['riskChallengeView']['riskResult'] == 'VERIFICATION'){
            $model->transId = $res['response']['body']['transId'];
            $model->securityId = $res['response']['body']['riskChallengeView']['securityId'];
            $model->eventLinkId = $res['response']['body']['riskChallengeView']['eventLinkId'];
            MemberDevice::where('device', $model->mobileNum)->update(['data' => json_decode(json_encode($model), true)]);
            return ['status' => 2,'message' => '簡訊驗證'];
        }
        return ['status' => -1,'message' => $res];
    }

    private static function getUnixTimestamp()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($s1) + floatval($s2)) * 1000);
    }

    //發送出款簡訊
    public function pay2($model)
    {
        $requestData = '[{"action":"VIEW","data":"{\"userId\":\"'.$model->user_id.'\"}","envData":"{\"appVersion\":\"2.3\",\"sdkVersion\":\"1.0.0\",\"deviceType\":\"android\",\"clientKey\":\"\",\"apdid\":\"eYOIklt7Qyza4sXSEs0qKcCYAhVZPG6xHSgRyYGT9GBFH1OEDN0gB5+L\",\"osVersion\":\"5.1.1\",\"deviceModel\":\"'.$model->X_PHONE_MODEL.'\",\"language\":\"en_US\",\"umidToken\":null,\"apdidToken\":\"5hqe6fr\\/Jrq33uO7E82pN8Nu5ZYNnXHu5sQGENUpEst7f+vHgQEAAA==\"}","isDisplaySensitiveField":false,"module":"INIT","verifyId":"'.$model->securityId.'"}]';

        $timestamp = $this->getUnixTimestamp();
        $headers = [
            'User-Agent: GCash/5.57.1: 707 Language/zh_TW',
            'tenantId: MYNTPH',
            'uuid: '.$this->guid(),
            'did: YsKKFauSFyUDALbefk7N1RwD',
            'workspaceId: PROD',
            'AppId: D54528A131559',
            'sessionId: '.$model->access_token,
            'Accept-Encoding: gzip',
            'ts: '.$timestamp,
            'clientId: 460007525224200|010067021561177',
            'sessionType: GCASH',
            'version: 1.0',
            'Accept-Language: zh-Hant',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
        ];
        $data = [
            'id' => 1,
            'operationType' => 'com.alipay.fc.riskcloud.dispatch',
            'requestData' => $requestData,
            'ts' => $timestamp,
        ];
        $data['sign'] = md5('b42bf7929d836ead7b9658e6ca18ae40&'.urldecode(http_build_query($data)));

        $res = json_decode($this->curl($this->gcash_url['rba_send'],http_build_query($data),$headers),true);
        Log::debug('GcashServcie pay2 response', compact('res'));
        return $res;
    }

    private static function guid(){
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }else{
            mt_srand(time());//optional for php 4.2.0 and up.
            $charid = md5(uniqid(rand(), true));
            $hyphen = chr(45);// "-"
            $uuid = substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12);// "}"
            return $uuid;
        }
    }

    public function pay3($model,$otp,$payto,$amount)
    {
        $requestData = '[{"action":"VERIFY","data":"{\"data\":\"'.$otp.'\"}","envData":"{\"appVersion\":\"2.3\",\"sdkVersion\":\"1.0.0\",\"deviceType\":\"android\",\"clientKey\":\"\",\"apdid\":\"eYOIklt7Qyza4sXSEs0qKcCYAhVZPG6xHSgRyYGT9GBFH1OEDN0gB5+L\",\"osVersion\":\"5.1.1\",\"deviceModel\":\"'.$model->X_PHONE_MODEL.'\",\"language\":\"en_US\",\"umidToken\":null,\"apdidToken\":\"5hqe6fr\\/Jrq33uO7E82pN8Nu5ZYNnXHu5sQGENUpEst7f+vHgQEAAA==\"}","isDisplaySensitiveField":false,"module":"otpSms","verifyId":"'.$model->securityId.'","version":"3.1.8.100"}]';

        $timestamp = $this->getUnixTimestamp();
        $headers = [
            'User-Agent: GCash/5.57.1: 707 Language/zh_TW',
            'tenantId: MYNTPH',
            'uuid: '.$this->guid(),
            'did: YsKKFauSFyUDALbefk7N1RwD',
            'workspaceId: PROD',
            'AppId: D54528A131559',
            'sessionId: '.$model->access_token,
            'Accept-Encoding: gzip',
            'ts: '.$timestamp,
            'clientId: 460007525224200|010067021561177',
            'sessionType: GCASH',
            'version: 1.0',
            'Accept-Language: zh-Hant',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
        ];
        $data = [
            'id' => 2,
            'operationType' => 'com.alipay.fc.riskcloud.dispatch',
            'requestData' => $requestData,
            'ts' => $timestamp,
        ];
        $data['sign'] = md5('b42bf7929d836ead7b9658e6ca18ae40&'.urldecode(http_build_query($data)));
        // $test = $this->getDetails($model);
        // if($test['status'] == false){
        //     return ['status'=>3,'message'=> 'Don\'t Login on your phone']; //出款失敗
        // }
        $res = json_decode($this->curl($this->gcash_url['rba_send'],http_build_query($data),$headers),true);

        if(isset($res['result']) && $res['result']['verifyCode'] == 'SUCCESS'){
            //審核出款
            $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"samsung","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'","currencyCode":"PHP","currencyValue":"'.$amount.'"}';
            $data = [
                'request' => [
                    'body' => [
                        'target' => $payto,
                        'amount' => $amount,
                        'message' => '',
                        'transId' => $model->transId,
                        'securityId' => $model->securityId,
                        'eventLinkId' => $model->eventLinkId,
                    ],
                    'method' => 'POST',
                    'header' => [
                        'X-Env-Info' => base64_encode($X_Info),
                        'X-Package-Id' => $this->PackageID,
                        'X-UDID' => $model->udid,
                        'X-FlowId' => $model->flowId,
                        'Authorization' => $model->access_token,
                        "X-Correlator-Id" => "iDzFwU6KYKPjNZ5TqNW5fAkPFc3V5gdY",
                    ],
                ],
                'sec' => [
                    'key' => '',
                    'initializer' => '',
                    'enc' => [
                    ],
                ],
            ];
            // dd($data);
            $post_data = base64_encode(json_encode($data));
            $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

            $test = $this->getDetails($model);
            if($test['status'] == false){
                return ['status'=>3,'message'=> 'Don\'t Login on your phone']; //出款失敗
            }
            $res = json_decode($this->curl($this->gcash_url['rba'],json_encode($post_json),$this->headers),true);
            Log::debug('GcashServcie pay3 response', compact('res'));
            //An error occurred during the request 重新發送一次
            if(isset($res['response']['body']['message']) && preg_match('/An error occurred during the request/',$res['response']['body']['message'])){
                $res = json_decode($this->curl($this->gcash_url['rba'],json_encode($post_json),$this->headers),true);
                Log::debug('GcashServcie pay3 retry', compact('res'));
            }
            if(isset($res['response']['body']['riskChallengeView']) && $res['response']['body']['riskChallengeView']['riskResult'] == 'ACCEPT' && $model->eventLinkId == $res['response']['body']['riskChallengeView']['eventLinkId']){
                sleep(1);
                $history = $this->getHistory($model); //
                $history = $history['response']['body']['transactions'] ?? [];
                $match_history = false;
                foreach($history as $k => $v){
                    if($v['referenceNo'] == $res['response']['body']['transId']){
                        $match_history = true;
                    }
                }
                if(!$match_history){
                    sleep(1);
                    $history = $this->getHistory($model);
                    $history = $history['response']['body']['transactions'] ?? [];
                    foreach($history as $k => $v){
                        if($v['referenceNo'] == $res['response']['body']['transId']){
                            $match_history = true;
                        }
                    }
                }
                if(!$match_history){
                    sleep(1);
                    $history = $this->getHistory($model);
                    $history = $history['response']['body']['transactions'] ?? [];
                    foreach($history as $k => $v){
                        if($v['referenceNo'] == $res['response']['body']['transId']){
                            $match_history = true;
                        }
                    }
                }
                if($match_history){
                    $model->transId = $res['response']['body']['transId'];
                    MemberDevice::where('device', $model->mobileNum)->update(['data' => json_decode(json_encode($model), true)]);
                    return ['status'=>1,'message'=>'出款成功'];
                }
                return ['status'=>3,'message'=> 'cheater','ref' => $res['response']['body']['transId'] ?? -1]; //出款失敗
            }else  if(isset($res['response']['body']['riskChallengeView']) && $res['response']['body']['riskChallengeView']['riskResult'] == 'ACCEPT' && $model->eventLinkId != $res['response']['body']['riskChallengeView']['eventLinkId']){
                return ['status'=>3,'message'=> 'cheater']; //出款失敗
            }else{
                if(isset($res['response']['body']['riskChallengeView']['riskResult'])){
                    $return = $res['response']['body']['riskChallengeView']['riskResult'];
                }else if(isset($res['response']['body']['message'])){
                    $return = $res['response']['body']['message'];
                }else{
                    $return = $res;
                }
                return ['status'=>3,'message'=>$return]; //出款失敗
            }
        }
        if(!isset($res['result']['verifyMessage'])){
            return ['status'=>3,'message'=> 'The payment was error,please resubmit']; //出款失敗
        }
        return ['status'=>2,'message'=>$res['result']['verifyMessage']];
    }

    public function daifu_pay3($model,$otp,$payto,$amount,$retry=true)
    {
        $requestData = '[{"action":"VERIFY","data":"{\"data\":\"'.$otp.'\"}","envData":"{\"appVersion\":\"2.3\",\"sdkVersion\":\"1.0.0\",\"deviceType\":\"android\",\"clientKey\":\"\",\"apdid\":\"eYOIklt7Qyza4sXSEs0qKcCYAhVZPG6xHSgRyYGT9GBFH1OEDN0gB5+L\",\"osVersion\":\"5.1.1\",\"deviceModel\":\"'.$model->X_PHONE_MODEL.'\",\"language\":\"en_US\",\"umidToken\":null,\"apdidToken\":\"5hqe6fr\\/Jrq33uO7E82pN8Nu5ZYNnXHu5sQGENUpEst7f+vHgQEAAA==\"}","isDisplaySensitiveField":false,"module":"otpSms","verifyId":"'.$model->securityId.'","version":"3.1.8.100"}]';

        $timestamp = $this->getUnixTimestamp();
        $headers = [
            'User-Agent: GCash/5.57.1: 707 Language/zh_TW',
            'tenantId: MYNTPH',
            'uuid: '.$this->guid(),
            'did: YsKKFauSFyUDALbefk7N1RwD',
            'workspaceId: PROD',
            'AppId: D54528A131559',
            'sessionId: '.$model->access_token,
            'Accept-Encoding: gzip',
            'ts: '.$timestamp,
            'clientId: 460007525224200|010067021561177',
            'sessionType: GCASH',
            'version: 1.0',
            'Accept-Language: zh-Hant',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
        ];
        $data = [
            'id' => 2,
            'operationType' => 'com.alipay.fc.riskcloud.dispatch',
            'requestData' => $requestData,
            'ts' => $timestamp,
        ];
        $data['sign'] = md5('b42bf7929d836ead7b9658e6ca18ae40&'.urldecode(http_build_query($data)));
        // $test = $this->getDetails($model);
        // if($test['status'] == false){
        //     return ['status'=>3,'message'=> 'Don\'t Login on your phone']; //出款失敗
        // }
        $res = json_decode($this->curl($this->gcash_url['rba_send'],http_build_query($data),$headers),true);

        if(isset($res['result']) && $res['result']['verifyCode'] == 'SUCCESS'){
            //審核出款
            $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"samsung","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'","currencyCode":"PHP","currencyValue":"'.$amount.'"}';
            $data = [
                'request' => [
                    'body' => [
                        'target' => $payto,
                        'amount' => $amount,
                        'message' => '',
                        'transId' => $model->transId,
                        'securityId' => $model->securityId,
                        'eventLinkId' => $model->eventLinkId,
                    ],
                    'method' => 'POST',
                    'header' => [
                        'X-Env-Info' => base64_encode($X_Info),
                        'X-Package-Id' => $this->PackageID,
                        'X-UDID' => $model->udid,
                        'X-FlowId' => $model->flowId,
                        'Authorization' => $model->access_token,
                        "X-Correlator-Id" => "iDzFwU6KYKPjNZ5TqNW5fAkPFc3V5gdY",
                    ],
                ],
                'sec' => [
                    'key' => '',
                    'initializer' => '',
                    'enc' => [
                    ],
                ],
            ];
            // dd($data);
            $post_data = base64_encode(json_encode($data));
            $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

            $test = $this->getDetails($model);
            if($test['status'] == false){
                return ['status'=>3,'message'=> 'Don\'t Login on your phone']; //出款失敗
            }
            $res = json_decode($this->curl($this->gcash_url['rba'],json_encode($post_json),$this->headers),true);
            //An error occurred during the request 重新發送一次
            if($retry == true && isset($res['response']['body']['message']) && preg_match('/An error occurred during the request/',$res['response']['body']['message'])){
                $res = json_decode($this->curl($this->gcash_url['rba'],json_encode($post_json),$this->headers),true);
            }
            if(isset($res['response']['body']['riskChallengeView']) && $res['response']['body']['riskChallengeView']['riskResult'] == 'ACCEPT' && $model->eventLinkId == $res['response']['body']['riskChallengeView']['eventLinkId']){
                $model->transId = $res['response']['body']['transId'];
                MemberDevice::where('device', $model->mobileNum)->update(['data' => json_decode(json_encode($model), true)]);
                return ['status'=>1,'message'=>'出款成功'];
            }else  if(isset($res['response']['body']['riskChallengeView']) && $res['response']['body']['riskChallengeView']['riskResult'] == 'ACCEPT' && $model->eventLinkId != $res['response']['body']['riskChallengeView']['eventLinkId']){
                return ['status'=>3,'message'=> 'cheater']; //出款失敗
            }else{
                if(isset($res['response']['body']['riskChallengeView']['riskResult'])){
                    $return = $res['response']['body']['riskChallengeView']['riskResult'];
                }else if(isset($res['response']['body']['message'])){
                    $return = $res['response']['body']['message'];
                }else{
                    $return = $res;
                }
                return ['status'=>3,'message'=>$return]; //出款失敗
            }
        }
        if(!isset($res['result']['verifyMessage'])){
            return ['status'=>3,'message'=> 'The payment was error,please resubmit']; //出款失敗
        }
        return ['status'=>2,'message'=>$res['result']['verifyMessage']];
    }

    public function mpinChange($model,$mpin,$newMpin)
    {
        $X_Info = '{"appVersion":"5.62.0:752","channel":"GCASH_APP","extendInfo":{"referenceId":"'.$model->refid.'","appVersion":"5.62.0:752","lbsErrorCode":"1003","phoneBrand":"'.$model->X_PHONE_BRAND.'","udid":"'.$model->udid.'","phoneModel":"'.$model->X_PHONE_MODEL.'","phoneManufacturer":"samsung","phoneOsVersion":"5.1.1,22","userAgent":"GCash App Android","dfpToken":"'.$model->X_DFP_TOKEN.'"},"orderTerminalType":"APP","osType":"Android","osVersion":"5.1.1","terminalType":"APP","tokenId":"'.$model->X_DFP_TOKEN.'","User-Agent":"GCash App Android","X-DFP-TOKEN":"'.$model->X_DFP_TOKEN.'","X-APP-VERSION":"5.62.0:752","X-PHONE-BRAND":"'.$model->X_PHONE_BRAND.'","X-PHONE-MANUFACTURER":"'.$model->X_PHONE_MANUFACTURER.'","X-PHONE-MODEL":"'.$model->X_PHONE_MODEL.'","X-PHONE-OS-VERSION":"5.1.1,22","X-UDID":"'.$model->udid.'","scenario_id":"first_time_otp"}';

        $data = [
            'request' => [
                'body' => [
                    'mpin' => $mpin,
                    'newMpin' => $newMpin,
                ],
                'method' => 'POST',
                'header' => [
                    'Authorization' => $model->access_token,
                    'X-Env-Info' => base64_encode($X_Info),
                    'X-FlowId' => $model->flowId,
                    'X-Package-Id' => $this->PackageID,
                    'X-UDID' => $model->udid,
                ],
            ],
            'sec' => [
                'key' => '',
                'initializer' => '',
                'enc' => [
                ],
            ],
        ];

        $post_data = base64_encode(json_encode($data));
        $post_json['aesKey'] = $this->aesKey;
        $post_json['iv'] = $this->aesIV;
        $post_json['sign'] = $this->getSign($post_data, $model->client_privateKey).'.'.$post_data;

        $res = json_decode(self::curl($this->gcash_url['change_mpin'],json_encode($post_json),$this->headers),true);
        Log::debug(__METHOD__, compact('mpin', 'newMpin', 'res'));

        if($res['response']['body']['code'] == 0){
            return ['status' => true,'message' => '修改成功'];
        }
        return ['status' => false,'message' => $res];
    }

    public static function curl($url, $data='',$ex_header=[]) {
        $useragent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:53.0) Gecko/20100101 Firefox/53.0';
        $header    = [

        ];
        if (is_array($ex_header) && count($ex_header) > 0) {
            $header = array_merge($header,$ex_header);
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_REFERER, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        $proxy = config('app.http_proxy');
        if ($proxy) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy);
        }

        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        $return = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_errno($curl);
        }
        curl_close($curl);
        return $return;

    }
}
