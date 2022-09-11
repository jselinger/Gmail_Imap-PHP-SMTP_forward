<?php
// JS 2022

## Creds and configs
require_once ('Config.php'); 

## Imports SMTP Mail Functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;##

require 'PHPMailer-6.6.4/src/Exception.php';
require 'PHPMailer-6.6.4/src/PHPMailer.php';
require 'PHPMailer-6.6.4/src/SMTP.php';


## DB Connect us path in Config
$db = new \PDO('sqlite:'.$dbpath, '', '', array(
    \PDO::ATTR_EMULATE_PREPARES => false,
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
));


#Imap Gmail Function from https://phppot.com/php/gmail-email-inbox-using-php-with-imap/

## Check for Imap Function
    if (! function_exists('imap_open')) {
        echo "IMAP is not configured.";
        exit();
    } else {
        ?>
<div id="listData" class="list-form-container">
    <?php
        
        /* Connecting Gmail server with IMAP  novalidate-cert  Creds in Config*/
        $connection = imap_open('{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX', $Imap_Username, $Imap_Password) or die('Cannot connect to Gmail: ' . imap_last_error());
        
        /* Search Emails having the specified keyword in the email subject */
        # $emailData = imap_search($connection, 'SUBJECT "Article "');
		$emailData = imap_search($connection, 'UNSEEN'); # Check Only new messages 
        
        if (! empty($emailData)) {
            ?>
    <table border="1">
        <?php
            foreach ($emailData as $emailIdent) {
                
                $overview = imap_fetch_overview($connection, $emailIdent, 0);
                #$message = imap_fetchbody($connection, $emailIdent, '1.1');  # this didnt work for Gmail
				$message = imap_fetchbody($connection, $emailIdent, '1');
				$message2 = imap_fetchbody($connection, $emailIdent, '2');
				$message3 = base64_decode ($message);
                $messageExcerpt = substr($message, 0, 150);
                $partialMessage = trim(quoted_printable_decode($messageExcerpt)); 
                $date = date("d F, Y", strtotime($overview[0]->date));
                ?>
        <tr>
            <td><span class="column">
                    <?php echo $overview[0]->from; ?>
            </span></td>
            <td class="content-div"><span class="column">
                    <?php echo $overview[0]->subject; ?> - <?php echo $partialMessage; ?> - <?php echo $overview[0]->udate; ?>
            </span><span class="date">
                    <?php echo $date; ?>
			</span><span class="message">
				<?php
				
				##the Table above and below does not need to be printed to the screen.
					#try all the differnt possible recived blocks looking for the Google Voice SMS part
					preg_match('/<td style="font-size: 14px; line-height: 20px; padding: 25px 0;">(.*?)\<\/td\>/', $message , $results1); #google Voice Parse 
					preg_match('/<td style="font-size: 14px; line-height: 20px; padding: 25px 0;">(.*?)\<\/td\>/', $message3, $results3); #google Voice Parse 
					preg_match('/<td style="font-size: 14px; line-height: 20px; padding: 25px 0;">(.*?)\<\/td\>/', $message2, $results2); #google Voice Parse 

					#preg_match('/<td style="font-size: 14px; line-height: 20px; padding: 25px 0;">(.*?)\<\/td\>/', $message , $results1a); #google Voice Parse 
					#preg_match('/<td style="font-size: 14px; line-height: 20px; padding: 25px 0;">(.*?)\<\/td\>/', $message3, $results3a); #google Voice Parse 
					#preg_match('/<td style="font-size: 14px; line-height: 20px; padding: 25px 0;">(.*?)\<\/td\>/', $message2, $results2a); #google Voice Parse 
					
					
					
					if 	     ($results1[1]!="")
					{
						AddtoSQL ($overview[0]->from, $Subject, $results1[1], $message, $message2, $date, 'R1');
					} else if($results3[1]!="") 
					{
						AddtoSQL ($overview[0]->from, $Subject, $results3[1], $message, $message2, $date, 'R3');
					} else if($results2[1]!="") 
					{
						AddtoSQL ($overview[0]->from, $Subject, $results2[1],  $message, $message2, $date, 'R2');
					} else 
					{
						AddtoSQL ($overview[0]->from, $Subject, $partialMessage, $message, $message2, $date, 'R0');
					}
					
				?>
            </span></td>
        </tr>
        <?php
            } // End foreach
            ?>
    </table>
    <?php
        } // end if
        imap_close($connection);
    }
    ?>
</div>

<hr>

<?php

function AddtoSQL($Sender, $Subject, $Body, $Message2, $Raw, $date, $dev=""){
	global $db;
	
	$SQLobj = $db->prepare("INSERT OR IGNORE INTO `mdm-mail` ('MID','sender','subject','message1','message2','raw','eTime','dev') 
				VALUES (NULL,:Sender,:subject,:message1,:message2,:raw,:eTime,:dev)");
	$SQLobj->bindValue('Sender', $Sender);
	$SQLobj->bindValue('subject', $Subject);
	$SQLobj->bindValue('message1', $Body);
	$SQLobj->bindValue('message2', $Message2);
	$SQLobj->bindValue('raw', $Raw);
	$SQLobj->bindValue('eTime', $date);
	$SQLobj->bindValue('dev', $dev);
	$SQLobj->execute();
}

function GetUnreadFromSQL()
{
	global $db;
	$SQLobj = $db->prepare('SELECT * FROM `mdm-mail` WHERE `sent`=0');
	$SQLobj->execute();
	$data = $SQLobj->fetchAll();
	return($data);
}


#Loop Get Unread messages and email
$themessages = GetUnreadFromSQL();
#print '<pre>'; print_r(GetUnreadFromSQL());print '</pre>';
##JS_PHPMailer_SMTP1($Subject, $Body)

function JS_PHPMailer_SMTP1($Subject, $Body)
{
	global $SMTP_SendingServer, $smtpSENDUsername, $smtpSENDPassword, $SMTP_FROM, $SMTP_TO;
	//Create an instance; passing `true` enables exceptions
	$mail = new PHPMailer(true);

	try {
		//Server settings
		#$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
		$mail->isSMTP();                                            //Send using SMTP
		$mail->Host       = $SMTP_SendingServer;                     //Set the SMTP server to send through
		$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
		$mail->Username   = $SMTP_Username;                     //SMTP username
		$mail->Password   = $SMTP_Password;                               //SMTP password
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
		$mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

		//Recipients
		$mail->setFrom($SMTP_FROM, 'Mail Bot');
		#$mail->addAddress($SMTP_TO, 'Joe User');     //Add a recipient
		$mail->addAddress($SMTP_TO);               //Name is optional
		#$mail->addReplyTo('info@example.com', 'Information');
		#$mail->addCC('cc@example.com');
		#$mail->addBCC('bcc@example.com');

		//Attachments
		#$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
		#$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

		//Content
		#$mail->isHTML(true);                                  //Set email format to HTML
		$mail->Subject = $Subject;
		$mail->Body    = $Body;
		#$mail->AltBody = $AltBody;

		$mail->send();
		echo ' #Message has been sent# ';
	} catch (Exception $e) {
		echo "<hr>Message could not be sent. Mailer Error: {$mail->ErrorInfo}<hr>";
	}
}
?>
<?php print "Done - ".date("F j, Y, g:i a"); ?>