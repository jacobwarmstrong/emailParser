<?php
//autoload dependencies
//ref this article for adding native classes to composer autoload
//https://phpenthusiast.com/blog/how-to-autoload-with-composer
require_once 'vendor/autoload.php';

//load our environment variables from our .env in the inc folder. These sensitive variables are left out of our code and repo
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dir = 'emails/archive/emails2/';

$files = scandir($dir);

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
        $contents = str_replace([' =', '=', '= ', '>', '<'], '', $contents );

        
        //received date
        $receivedDate = strstr($contents, 'Received');
        if(strpos($receivedDate, 'X-Google-Smtp-Source')) {
            $receivedDate = strstr($receivedDate, 'X-Google-Smtp-Source', TRUE);
        } 
        if (strpos($receivedDate, 'X-Received')) {
            $receivedDate = strstr($receivedDate, 'X-Received', TRUE);
        }
        $receivedDate = strstr($receivedDate, '; ');
        $receivedDate = str_replace('; ', '', $receivedDate);
        $addDate = date('m/d/Y', strtotime($receivedDate));
        if($addDate == '01/01/1970'){
            echo $receivedDate . '<br><br>';
        }
        echo $addDate . '<br>';
        $contact['addDate'] = $addDate;
        
        $lead = strstr($contents, 'New Lead');
        //echo $lead . '<br>';

        //fullName
        $name = strstr( strstr($lead,'Name:'), 'Email:', TRUE);
        $name = str_replace(['Name: '], '' , $name);
        $name = trim($name);
        $name = ucwords( strtolower($name) );
        $contact['fullName'] = $name;

        //email
        $email = strstr( strstr($lead,'Email:'), 'Email ', TRUE);
        $email = str_replace(['Email: '], '' , $email);
        $email = trim($email);
        $email = strtolower($email);
        //fixes imcomplete email for yahoo. Can this be smarter? Or probably should just validate when user is inputting..
        if(strpos($email, '@yahoo')) {
            $email = str_replace('@yahoo', '@yahoo.com', $email);
        }
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
            $contact['phone'] = null;
        } else {
            $phone = substr_replace( $phone, '-', 3, 0);
            $phone = substr_replace( $phone, '-', 7, 0);
            $contact['phone'] = $phone;
        }

        //location
        $location = strstr( strstr($lead,'Location: '), 'Interested In:', TRUE);
        $location = str_replace(['Location: '], '' , $location);
        $location = trim($location);
        $location = ucwords(strtolower($location));
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
        
        if($contact['email'] == '') {
            echo 'Contact does not have a email, cannot upload to Mailchimp.';
            var_dump($contact);
        } else {
            array_push($audience, $contact);  
        }
 
    } 
}

echo '<h1>Number of Contacts Queued</h1>';
echo '<p>' . count($audience) . '</p>';

$api_success = 0;

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
        'INTEREST' => "{$contact['interest']}",
        'ADD_DATE' => "{$contact['addDate']}"
    ]
]);

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
        var_dump($contact);
    } else {
        $api_success ++;
    }
}

echo '<h1>Successful API Uploads</h1>';
echo '<p>' . $api_success . '</p>';

