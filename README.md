# Simple DeathByCaptcha PHP Class
A very simple class that uses the DeathByCaptcha API to solve Captchas.

### Usage Example
```
require_once('deathbycaptcha.class.php');

try {
    $dbc = new DeathByCaptcha('username', 'password');
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
```

### Functions

#### $dbc -> __construct(username, password, [cURLHandle])
Creates a DeathByCaptcha object with your username and password.
It does not try to login with these details as that would require an extra request.
You can pass an optional cURL handle which will be used instead of making a new one.

#### $dbc -> getBalance()
This function gets our balance, and will also report if the username-password combination is correct or not.
This should be used to check if we have any credits left before we send the captcha to DBC.
It returns false if it's unable to grab the balance, or the balance on success.

#### $dbc -> setCaptchaFromImage(image)
Gives our object a Captcha image to work with. At the moment only 1 Captcha definition per object is available.
Make sure that the class has the correct permissions to access the file.
Returns true/false.

#### $dbc -> setCaptchaFromURL(imageURL, [cookies, postfields])
Grabs a captcha from a specified URL and uses it in our object.
If the cookies parameter is set then our cURL handle will use the specified cookies in the request by using CURLOPT_COOKIE.
If the postfields parameter is set then we will send a POST request by using CURLOPT_POSTFIELDS.
Returns true/false.

#### $dbc -> submitCaptcha()
Submits our Captcha to the DeathByCaptcha API and sets the captchaID in the object.
Returns true/false.

#### $dbc -> getCaptchaText([delay, maxTries])
This function will loop and check to see if our Captcha has been solved or not.
You can pass an optional delay parameter to delay the time between requests. (default: 4)
The maxTries parameter is used to specifiy the amount of times we will check if the Captcha is solved (default: 15)

#### $dbc -> checkCaptcha()
This function checks a single time if our captcha has been solved or not.
It's used by the getCaptchaText() function in it's loop.
This might be useful in some cases, although you'll probably end up using getCaptchaText().

#### $dbc -> cURL_opt(option, [value])
You can easily change the options for our cURL request by using this function.
If the option parameter is an array, we will use curl_setopt_array() instead of curl_setopt() and you'll not have to specify the value.
Remember to check the class to see where you should change the curlOpts in your code so nothing breaks.

Have fun.