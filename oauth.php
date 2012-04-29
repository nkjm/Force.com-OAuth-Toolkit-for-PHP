<?php
/*
 * You can freely use, embed, modify and re-distribute this program. No warranty.
 * Copyright Kazuki Nakajima <nkjm.kzk@gmail.com>
 */

class oauth {
    public $client_id;
    public $client_secret;
    public $login_url;
    public $token_url;
    public $callback_url;
    public $access_token;
    public $refresh_token;
    public $instance_url;
    public $cache_dir;
    public $error = FALSE;
    public $error_msg = array();

    public function __construct($client_id, $client_secret, $callback_url, $login_url = 'https://login.salesforce.com', $cache_dir = '.'){
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->callback_url = $callback_url;
        $this->login_url = $login_url;
        $this->token_url = $login_url . "/services/oauth2/token";
        $this->cache_dir = $cache_dir;
    }

    public function auth_with_code($lifetime = 60){
        session_start();
        $this->read_cache_from_session();
        $this->refresh_cache_on_session($lifetime);
        if ($this->error){
            return(FALSE);
        }
        if (empty($this->access_token) || empty($this->instance_url) || empty($this->refresh_token)){
            //get access code
            if (!isset($_GET['code'])){
                $this->redirect_to_get_access_code();
            }

            //set user display depending on user agent
            if ($this->get_user_agent() == 'iPhone' || $this->get_user_agent() == 'iPad'){
                $display = 'touch';
            } else {
                $display = 'page';
            }

            //get access token
            $fragment = "grant_type=authorization_code"
            . "&code=" . $_GET['code']
            . "&display=" . $display
            . "&client_id=" . $this->client_id
            . "&client_secret=" . $this->client_secret
            . "&redirect_uri=" . urlencode($this->callback_url);
            $response = $this->send($fragment);
            if ($this->error){
                if (array_pop($this->error_msg) == 'new code required'){
                    $this->redirect_to_get_access_code();
                } else {
                    return(FALSE);
                }
            }
            $this->access_token = $response['access_token'];
            $this->refresh_token = $response['refresh_token'];
            $this->instance_url = $response['instance_url'];
            $this->save_to_session();
        }
        return(TRUE);
    }

    public function auth_with_password($username, $password, $lifetime = 60){
        $this->refresh_cache_on_filesystem($lifetime);
        if ($this->error){
            return(FALSE);
        }
        $this->read_cache_from_filesystem();
        if ($this->error){
            return(FALSE);
        }
        if (empty($this->access_token) || empty($this->instance_url)){
            $fragment = "grant_type=password"
            . "&client_id=" . $this->client_id
            . "&client_secret=" . $this->client_secret
            . "&username=" . $username
            . "&password=" . $password;
            $response = $this->send($fragment);
            if ($this->error){
                return(FALSE);
            }
            $this->access_token = $response['access_token'];
            $this->refresh_token = '';
            $this->instance_url = $response['instance_url'];
            $this->save_to_filesystem();
            if ($this->error){
                return(FALSE);
            }
        }
        return(TRUE);
    }

    public function auth_with_refresh_token(){
        $fragment = "grant_type=refresh_token"
        . "&client_id=" . $this->client_id
        . "&client_secret=" . $this->client_secret
        . "&refresh_token=" . $this->refresh_token;
        $response = $this->send($fragment);
        if ($this->error){
            return(FALSE);
        }
        $this->access_token = $response['access_token'];
        $this->save_to_session();
        return(TRUE);
    }

    private function get_user_agent(){
        $f = explode(';', $_SERVER['HTTP_USER_AGENT']);
        $ff = explode('(', $f[0]);
        return(trim($ff[1]));
    }

    private function redirect_to_get_access_code(){
        $auth_url = $this->login_url . "/services/oauth2/authorize?response_type=code&client_id=" . $this->client_id . "&redirect_uri=" . urlencode($this->callback_url);
        header('Location: ' . $auth_url);
    }

    private function redirect_to_get_access_token(){
        $auth_url = $this->login_url . "/services/oauth2/authorize?response_type=token&client_id=" . $this->client_id . "&redirect_uri=" . urlencode($this->callback_url);
        header('Location: ' . $auth_url);
    }

    private function refresh_cache_on_session($lifetime){
        if (isset($_SESSION['created_at'])){
            $current_time = time();
            if (($current_time - $_SESSION['created_at']) > $lifetime * 60){
                $this->auth_with_refresh_token();
            }
        }
    }

    private function refresh_cache_on_filesystem($lifetime){
        if (is_file($this->cache_dir . "/access_token")){
            $current_time = time();
            $mtime = filemtime($this->cache_dir . "/access_token");
            if (($current_time - $mtime) > $lifetime * 60){
                if (!unlink($this->cache_dir . "/access_token")){
                    $this->set_error("Failed to unlink " . $this->cache_dir . "/access_token.");
                    return;
                }
            }
        }
    }

    private function read_cache_from_filesystem(){
        $array_cache = array("access_token", "refresh_token", "instance_url");
        foreach($array_cache as $k => $v){
            if (is_file($this->cache_dir . "/" . $v)){
                $fp = fopen($this->cache_dir . "/" . $v, "r");
                if ($fp == FALSE){
                    $this->set_error("Failed to open " . $this->cache_dir . "/" . $v . " in read mode.");
                    return;
                }
                $this->$v = fgets($fp);
                fclose($fp);
            }
        }
    }

    private function read_cache_from_session(){
        $array_cache = array("access_token", "refresh_token", "instance_url");
        foreach($array_cache as $k => $v){
            if (isset($_SESSION[$v])){
                $this->$v = $_SESSION[$v];
            }
        }
    }

    private function send($fragment){
        $curl = curl_init($this->token_url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fragment);
        $response = json_decode(curl_exec($curl), true);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status == 400 && $response['error_description'] == 'expired authorization code') {
            //access code has been expired
            $this->set_error('new code required');
        } elseif ( $status != 200 ) {
            $this->set_error("<h1>Curl Error</h1><p>URL : $this->token_url </p><p>Status : $status</p><p>response : error = " . $response['error'] . ", error_description = " . $response['error_description'] . "</p><p>curl_error : " . curl_error($curl) . "</p><p>curl_errno : " . curl_errno($curl) . "</p>");
        }
        curl_close($curl);
        return($response);
    }

    private function save_to_filesystem(){
        $array_cache = array("access_token", "refresh_token", "instance_url");
        foreach($array_cache as $k => $v){
            $fp = fopen($this->cache_dir . "/" . $v, "w");
            if ($fp == FALSE){
                $this->set_error("Failed to open " . $this->cache_dir . "/" . $v . " in write mode.");
                return;
            }
            fwrite($fp, $this->$v);
            fclose($fp);
        }
    }

    private function save_to_session(){
        $array_cache = array("access_token", "refresh_token", "instance_url");
        foreach($array_cache as $k => $v){
            $_SESSION[$v] = $this->$v;
        }
        $_SESSION['created_at'] = time();
    }

    private function set_error($error_msg){
        $this->error = TRUE;
        array_push($this->error_msg, $error_msg);
    }

    public function trace_error(){
        if ($this->error){
            foreach ($this->error_msg as $k => $v){
                print '<p>' . $v . '</p>';
            }
        }
    }
}
?>
