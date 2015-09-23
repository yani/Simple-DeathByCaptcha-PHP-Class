<?php
require_once('deathbycaptcha.class.php');

try {
    $dbc = new DeathByCaptch('username', 'password');
    if($dbc->getBalance() > 0){
        if($dbc->setCaptchaFromImage('captcha.png')){
            if($dbc->submitCaptcha()){
                echo $dbc->getCaptchaText();
            }
        }
    }
} catch(Exception $ex) {
    echo "<pre>$ex</pre>";
}