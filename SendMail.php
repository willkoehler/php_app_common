<?

// disable deprecated warning messages - pear mail has code that is deprecated in php 5.3
// (E_DEPRECATED is only defined in php 5.3 or higher)
if(defined("E_DEPRECATED")) 
{
    error_reporting(E_ALL ^ E_DEPRECATED);
}
// --- Email notification uses PEAR Mail (http://pear.php.net/)
require_once "Mail.php";
error_reporting(E_ALL);                     // re-enable warning messages

require_once "AWS-SES.php";

//----------------------------------------------------------------------------------
//  SendMail()
//
//  Sends email message using Amazon SES
//
//  NOTE: This function assumes connection to database has been previously
//  established by calling OpenDataConnection()
//
//  PARAMETERS:
//    to      - address to send email to. May contain multiple addresses separated by
//              commas
//    subject - subject line of email
//    body    - text for body of the email
//    from    - address email will come from (will also be in reply-to field)
//
//  RETURN: none
//-----------------------------------------------------------------------------------
function SendMail($to, $subject, $body, $from)
{
    $ses = new SimpleEmailService('xXxXxXxXxXx', 'xXxXxXxXxXx');
    $m = new SimpleEmailServiceMessage();
    $to = explode(',', $to);  // convert $to from comma separated list into array
    foreach($to as $recipient)  $m->addTo(trim($recipient));
    $m->setFrom($from);
    $m->setSubject($subject);
    $m->setMessageFromString($body);
    $result = $ses->sendEmail($m);
    return ($result==false) ? false : true;
}


//----------------------------------------------------------------------------------
//  SendMail()
//
//  Sends email message using local email server. This method is needed if the from
//  address is not verified by SES (ex video jobs system)
//
//  NOTE: This function assumes connection to database has been previously
//  established by calling OpenDataConnection()
//
//  PARAMETERS:
//    oDB     - mysqli database connection object
//    to      - address to send email to. May contain multiple addresses separated by
//              commas
//    subject - subject line of email
//    body    - text for body of the email
//    from    - address email will come from (will also be in reply-to field)
//
//  RETURN: none
//-----------------------------------------------------------------------------------
function SendMailOld($oDB, $to, $subject, $body, $from)
{
    $rs = $oDB->query("SELECT * FROM system_config", __FILE__, __LINE__);
    $emailConfig = $rs->fetch_assoc();

    $host = $emailConfig['SMTPServer'];
    $port = $emailConfig['SMTPPort'];
    $auth = $emailConfig['SMTPAuthenticate'];
    $username = $emailConfig['SMTPUsername'];
    $password = $emailConfig['SMTPPassword'];

    if(!$from)
    {
        $from = $emailConfig['SMTPFromEmail'];
    }

    if($auth == true)
    {
    // --- SMTP server requires authentication
        $smtp = Mail::factory('smtp', array ('host' => $host,
                                             'port' => $port,
                                             'auth' => true,
                                             'username' => $username,
                                             'password' => $password,
                                             'persist' => true));
    }
    else
    {
    // --- SMTP server does not require authentication
        $smtp = Mail::factory('smtp', array ('host' => $host,
                                             'port' => $port,
                                             'auth' => false,
                                             'persist' => true));
    }
    $headers = array ('From' => $from, 'To' => $to, 'Subject' => $subject);
    $mail = $smtp->send($to, $headers, $body);

    if (PEAR::isError($mail))
    {
        return($mail->getMessage());
    }
    else
    {
        return("OK");
    }
}
?>