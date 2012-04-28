PREREQUISITES
==============
- Session Support in your PHP
- cURL Support in your PHP
- JSON SUpport in your PHP
- SSL Support in your Web Server

HOW TO INSTALL
==============
Step 1. Include oauth.php in php script which needs authentication.
-------------------------------------------------------------------
    require_once 'oauth.php';

Step 2. Create a new instance of oauth class.
---------------------------------------------
    $oauth = new oauth([CLIENT_ID], [CLIENT_SECRET], [CALLBACK_URL], [LOGIN_URL], [CACHE_DIR]);

- LOGIN_URL and CACHE_DIR are optional.
- access token, refresh token and instance url are saved under CACHE_DIR in case of using auth_with_password() method.

Step 3. Do OAuth. 
------------------------------------------------------------------------------------------------------
    $oauth->auth_with_code([LIFETIME]);
If you apply standard web server authetication flow, you can use auth_with_code() method. This type of flow is appropriate when you provide external web services that need access to Force.com/Database.com. LIFETIME is minutes to refresh access token. You can omit LIFETIME and then it is set to 60 by default.

    $oauth->auth_with_password([USERNAME], [PASSWORD]);
If you apply username/password authetication flow, use auth_with_password() method. This type of flow is used in case you need users access to web contents without authentication while the contents still need login to Force.com/Database.com.

Step 4. Create a directory for CACHE_DIR
----------------------------------------------------------------
If you apply username/password authentication flow, Token and Instance URL info will be saved in individual file under the directory which is configured as CACHE_DIR after initial authentication succeeds. Default location of CACHE_DIR is current directory. UNIX web user must has write permission on this directory or you can set another directory by passing 5th argument in creating $oauth instance as follows.

    define('CLIENT_ID', '3MVG9rFJvQRVOvk40dRq5u_ZA0eT2KvZCvZq.XeA1hFtgc3PITGlLMp3V_kKIwtc6IaEGWkIO3cOu0IgVmujh');
    define('CLIENT_SECRET', '1136279981407985294');
    define('CALLBACK_URL', 'https://sugoisurvey.nkjmkzk.net');
    define('LOGIN_URL', 'https://login.salesforce.com');
    define('CACHE_DIR', 'oauth/cache');
    $oauth = new oauth(CLIENT_ID, CLIENT_SECRET, CALLBACK_URL, LOGIN_URL, CACHE_DIR);

Cache files are refreshed every 60 minutes by default. You can set other value by passing 3rd argument in executing auth_with_password() as follows.

    define('USERNAME', 'nkjm.kzk@gmail.com');
    define('PASSWORD', 'mypassword');
    $oauth->auth_with_password(USERNAME, PASSWORD, 120);

Step 5. Use Token to access REST Resources.
-----------------------------------------------
After auth_with_code() or auth_with_password() successfully executed, $oauth instance has following properties set.
You can use these values to access to REST Resources.

- $oauth->access_token
- $oauth->refresh_token
- $oauth->instance_url

Sample Code
===========
Following is the sample code which describe all the required code to provide web server authentication flow.

    <?php
    require_once "oauth.php";

    // You have to change following paramenter depending on your remote access setting.
    define('CLIENT_ID', '3MVG9rFJvQRVOvk40dRq5u_ZA0eT2KvZCvZq.XeA1hFtgc3PITGlLMp3V_kKIwtc6IaEGWkIO3cOu0IgVmujh');
    define('CLIENT_SECRET', '1136279981407985294');
    define('CALLBACK_URL', 'https://sugoisurvey.nkjmkzk.net');

    $oauth = new oauth(CLIENT_ID, CLIENT_SECRET, CALLBACK_URL);
    $oauth->auth_with_code();
    
    $query = "select name from session__c";
    $url = $oauth->instance_url . "/services/data/v24.0/query?q=" . urlencode($query);
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth " . $oauth->access_token));
    $response = json_decode(curl_exec($curl), true);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ( $status != 200 ) {
        die("<h1>Curl Error</h1><p>URL : " . $url . "</p><p>Status : " . $status . "</p><p>response : error = " . $response['error'] . ", error_description = " . $response['error_description'] . "</p><p>curl_error : " . curl_error($curl) . "</p><p>curl_errno : " . curl_errno($curl) . "</p>");
    }
    curl_close($curl);
    return($response);
    ?>

Learn about OAuth 2.0 in Force.com
==================================
Following blog article should be a great reference to understand each type of authentication flow in OAuth 2.0.
[Digging Deeper into OAuth 2.0 on Force.com](http://wiki.developerforce.com/page/Digging_Deeper_into_OAuth_2.0_on_Force.com)
