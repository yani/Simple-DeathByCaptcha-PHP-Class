<?php
/*
 * Simple PHP Class for the Death By Captcha API.
 * http://www.deathbycaptcha.com/
 *
 * Version: 1.0.1
 *
 * Author: Yani
 * https://github.com/Yanikore
 */

class DeathByCaptcha
{
    public $username = false;
    public $password = false;

    public $useragent = "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0";
    private $mimeTypes = array('image/png', 'image/jpg', 'image/gif', 'image/jpeg');

    public $cURL = false;
    private $base64Captcha = false;
    private $overLoaded = false;
    public $captchaID;


    public function __construct($username, $password, $curl = false)
    {
        if (empty($username) || empty($password)) {
            throw new Exception('DBC: Login details not set');
        }

        if (!function_exists('curl_init') || !defined('CURL_HTTP_VERSION_1_0')) {
            throw new Exception('DBC: cURL not enabled');
        }

        $this->username = urlencode($username);
        $this->password = urlencode($password);

        if ($curl) {
            $this->cURL = $curl;
        } else {
            $this->cURL_init();
        }

        set_time_limit(0);
    }

    public function cURL_opt($opt, $value = null)
    {
        if (!$this->cURL) {
            throw new Exception('DBC: cURL not initialized');
        }

        if (is_array($opt)) {
            if (!curl_setopt_array($this->cURL, $opt)) {
                throw new Exception('DBC: Failed to set cURL options');
            }
        } else {
            if (!curl_setopt($this->cURL, $opt, $value)) {
                throw new Exception('DBC: Failed to set cURL option: ' . $opt);
            }
        }
    }

    private function cURL_init()
    {
        if ($this->cURL) {
            @curl_close($this->cURL);
        }

        if (!$this->cURL = @curl_init()) {
            throw new Exception('DBC: cURL failed to initialize');
        }

        $cURLopts = array(
            CURLOPT_COOKIEFILE => "",
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        );

        if (!curl_setopt_array($this->cURL, $cURLopts)) {
            throw new Exception('DBC: Failed to set cURL options');
        }
    }

    public function getBalance()
    {
        $this->cURL_opt(array(
            CURLOPT_URL => 'http://api.dbcapi.me/api/user',
            CURLOPT_POSTFIELDS => 'username=' . $this->username . '&password=' . $this->password
        ));

        if (!$res = curl_exec($this->cURL)) {
            return false;
        }

        if (curl_getinfo($this->cURL, CURLINFO_HTTP_CODE) != 200) {
            throw new Exception('DBC: Failed to authenticate, possibly wrong login info');
        }

        if (!$arr = $this->_explodeResp($res)) {
            return false;
        }

        if (!empty($arr['is_banned']) && $arr['is_banned'] == '1') {
            throw new Exception('DBC: Account has been banned');
        }

        return (!empty($arr['balance']) && is_numeric($arr['balance'])) ? floatval($arr['balance'] / 100) : false;
    }

    public function setCaptchaFromURL($url, $cookies = false, $post = false)
    {
        $this->cURL_opt(array(
            CURLOPT_URL => $url,
            CURLOPT_POST => false
        ));

        if ($cookies) {
            $this->cURL_opt(CURLOPT_COOKIE, $cookies);
        }

        if ($post) {
            $this->cURL_opt(CURLOPT_POSTFIELDS, $post);
        }

        if (!$res = curl_exec($this->cURL)) {
            return false;
        }

        if (!$mime = curl_getinfo($this->cURL, CURLINFO_CONTENT_TYPE)) {
            return false;
        }

        if (!in_array(strtolower($mime), $this->mimeTypes)) {
            return false;
        }

        $this->base64Captcha = base64_encode($res);
        return true;
    }

    public function setCaptchaFromImage($img)
    {
        if (file_exists($img)) {
            if (function_exists('finfo_file')) {
                $fnfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = strtolower(@finfo_file($fnfo, $img));

                if (!in_array($mime, $this->mimeTypes)) {
                    return false;
                }
            }

            if (($imgData = @file_get_contents($img)) !== false) {
                $this->base64Captcha = base64_encode($imgData);
                return true;
            }
        } else {
            throw new Exception('DBC: Captcha image not found');
        }

        return false;
    }

    public function submitCaptcha()
    {
        if (!$this->base64Captcha) {
            return false;
        }

        $this->cURL_opt(array(
            CURLOPT_URL => 'http://api.dbcapi.me/api/captcha',
            CURLOPT_POSTFIELDS => 'username=' . $this->username . '&password=' . $this->password . '&captchafile=base64:' . urlencode($this->base64Captcha)
        ));

        if (!$res = curl_exec($this->cURL)) {
            return false;
        }

        $returnCode = curl_getinfo($this->cURL, CURLINFO_HTTP_CODE);
        if ($returnCode != 200) {
            if ($returnCode == 403) {
                throw new Exception('DBC: Failed to authenticate, possibly wrong login info');
            }

            return false;
        }

        if (!$arr = $this->_explodeResp($res)) {
            return false;
        }

        if (empty($arr['captcha']) || !is_numeric($arr['captcha']) || $arr['captcha'] < 1) {
            return false;
        }

        $this->captchaID = $arr['captcha'];

        return true;
    }

    public function checkCaptcha()
    {
        if (!$this->captchaID) {
            return false;
        }

        $this->cURL_opt(array(
            CURLOPT_URL => 'http://api.dbcapi.me/api/captcha/' . $this->captchaID,
            CURLOPT_POST => false
        ));

        if (!$res = curl_exec($this->cURL)) {
            return false;
        }

        if (curl_getinfo($this->cURL, CURLINFO_HTTP_CODE) != 200) {
            return false;
        }

        if (!$arr = $this->_explodeResp($res)) {
            return false;
        }

        return (!empty($arr['text'])) ? str_replace('+', ' ', $arr['text']) : false;
    }

    public function getCaptchaText($delay = 4, $tries = 15)
    {
        for ($i = 0; $i < $tries; $i++) {
            $return = $this->checkCaptcha();

            if ($return) {
                return $return;
            }

            sleep($delay);
        }

        return false;
    }

    private function _explodeResp($res)
    {
        if (stripos($res, '&') === false) {
            return false;
        }

        $arr = explode('&', trim($res));

        foreach ($arr as $varVal) {
            if (stripos($varVal, '=') !== false) {
                $arr[explode('=', $varVal)[0]] = explode('=', $varVal)[1];
            }
        }

        return (count($arr) > 0) ? $arr : false;
    }
}
