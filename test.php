// Create a test.php file with this content:
<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:9200');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, 'elastic:MIkro@123');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if(curl_errno($ch)) {
    die('CURL Error: '.curl_error($ch));
}
curl_close($ch);
echo $response;
