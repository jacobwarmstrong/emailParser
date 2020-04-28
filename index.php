<?php
//autoload dependencies
//ref this article for adding native classes to composer autoload
//https://phpenthusiast.com/blog/how-to-autoload-with-composer
require_once 'vendor/autoload.php';

//load our environment variables from our .env in the inc folder. These sensitive variables are left out of our code and repo
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dir = 'emails/2012142019leads/';

$files = scandir($dir);

var_dump($files);

$audience = [];

foreach($files as $file) {
    //if hidden directory file, skip it
    if(strpos($file,'.') === 0) {
        continue;
    } else {
        $contact = [];
        $contents = file_get_contents($dir . $file);

        $contents = strip_tags($contents);
        $contents = preg_replace( "/\r|\n/", "", $contents );
        $contents = str_replace([' =', '=', '= '], '', $contents );
        $lead = strstr($contents, 'New Lead');
        //echo $lead . '<br>';

        //fullName
        $name = strstr( strstr($lead,'Name:'), 'Email:', TRUE);
        $name = str_replace(['Name: '], '' , $name);
        $name = ucwords( strtolower($name) );
        $contact['fullName'] = $name;

        //email
        $email = strstr( strstr($lead,'Email:'), 'Email ', TRUE);
        $email = str_replace(['Email: '], '' , $email);
        $email = strtolower($email);
        //echo $email . '<br>';
        $contact['email'] = $email;

        //email sub
        $sub = strstr( strstr($lead,'Email Subscription:'), 'Phone:', TRUE);
        //echo ('sub: ' . $sub);
        $sub = str_replace('Email Subscription: ', '', $sub);
        if(strstr($sub, 'on')) {
            $contact['subscription'] = 'subscribed';
        } else {
            $contact['subscription'] = 'unsubscribed';
        }

        //phone
        $phone = strstr( strstr($lead,'Phone: '), 'Location:', TRUE);
        $phone = str_replace(['Phone: ', '-', '(', ')'], '' , $phone);
        if (strlen($phone) != 10) {
            echo $phone;
            $contact['phone'] = null;
        } else {
            $phone = substr_replace( $phone, '-', 3, 0);
            $phone = substr_replace( $phone, '-', 7, 0);
            $contact['phone'] = $phone;
        }

        //location
        $location = strstr( strstr($lead,'Location: '), 'Interested In:', TRUE);
        $location = str_replace(['Location: '], '' , $location);
        $contact['location'] = $location;

        //interest
        $interest = strstr( strstr($lead,'Interested In: '), 'Comments:', TRUE);
        $interest = str_replace(['Interested In: '], '' , $interest);
        //echo $interest . '<br>';
        $contact['interest'] = $interest;

        //comment
        $comments = strstr($lead,'Comments: ');
        $comments = str_replace(['Comments: '], '' , $comments);
        $comments = str_replace(['\"'], '"' , $comments);
        $comments = str_replace(["\'"], "'" , $comments);
        $contact['comments'] = $comments;

        var_dump($contact);
        array_push($audience, $contact);   
    } 
}

//var_dump($audience);
exit;

foreach($audience as $contact) {
//send contact to Mailchimp account, borrowed from http://www.codexworld.com/add-subscriber-to-list-mailchimp-api-php/
// MailChimp API credentials
$apiKey = getenv('MAILCHIMP_API');
$listID = getenv('MAILCHIMP_AUDIENCE_KEY');

// MailChimp API URL
$memberID = md5(strtolower($contact['email']));
$dataCenter = substr($apiKey,strpos($apiKey,'-')+1);
$url = 'https://' . $dataCenter . '.api.mailchimp.com/3.0/lists/' . $listID . '/members/' . $memberID;

// member information
$json = json_encode([
    'email_address' => "{$contact['email']}",
    'status'        => "{$contact['subscription']}",
    'merge_fields' => [
        'FNAME' => "{$contact['fullName']}",
        'PHONE' => "{$contact['phone']}",
        'LOCATION' => "{$contact['location']}",
        'INTEREST' => "{$contact['interest']}"
    ]
]);

var_dump($json);

// send a HTTP POST request with curl
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $apiKey);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 0);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

    if ($httpCode != 200) {
        echo 'Contact  ' . $contact['fullName'] . ' returned a ' . $httpCode . '<br>';
    } else {
        echo 'CONGRATULATIONS MOFO' . '<br>';
    }
}
