<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NlpImap
{
  
  const TEXT = 0;
  const MULTIPART = 1;
  const PLAIN = 2;
  private array $messageType = [0 => 'Text', 1 => 'Multipart', 2 => 'Plain'];
  
  public array $minivanSubjects = [
    'nlpresponsesexport' => 'survey' ,
    'nlpcontactsexport' => 'canvass',
    'nlpactivistcodesexport' => 'activist',
    'nlpnotesexport' => 'note',
  ];
  
  public array $matchbackSubject = ['matchback' => 'matchback',];
  
  /**
   * Don't retrieve a message part larger than this size in bytes, as it's
   * unlikely to be text, and we're only interested in text.
   */
  const MAXMAILPARTSIZEBYTES = 20480;
  
  protected ConfigFactoryInterface $config;
  
  public function __construct( $config ) {
    $this->config = $config;
  }
  
  public static function create(ContainerInterface $container): NlpImap
  {
    return new static(
      $container->get('config.factory'),
    );
  }
  
  /**
   * @param $email
   * @return resource|null
   */
  public function getImapConnection($email)
  {
    $messenger = Drupal::messenger();
    $nlpConfig = $this->config->get('nlpservices.configuration');
    $emailConfiguration = $nlpConfig->get('nlpservices-email-configuration');
    $emailInfo = $emailConfiguration[$email];
    //nlp_debug_msg('$emailInfo',$emailInfo);
    $hostname = '{'.$emailInfo['server'].':'.$emailInfo['port'].'/imap/ssl/novalidate-cert}INBOX';
    //nlp_debug_msg('$hostname',$hostname);
    $connection = imap_open($hostname,$emailInfo['email'],$emailInfo['password']);
    if(empty($connection)) {
      $error = imap_last_error();
      $messenger->addError('connection: '. $error);
      nlp_debug_msg('imap_open error',$error);
      return NULL;
    }
    return $connection;
  }
  
  /**
   * @param $parameters
   * @param $attribute
   * @param $emailNumber
   * @return array
   */
  private function getAttachment($parameters,$attribute,$emailNumber): array
  {
    $attachment = [];
    //nlp_debug_msg('parameters',$parameters);
    foreach($parameters as $object) {
      if(strtolower($object->attribute) == $attribute) {
        if(!empty($object->value)) {
          $attachment['is_attachment'] = true;
          $attachment['filename'] = $object->value;
          $attachment['emailNumber'] = $emailNumber;
        }
      }
    }
    return $attachment;
  }
  
  /**
   * @param $connection
   * @param $emailType
   * @return array
   */
  public function getNewEmailsToProcess($connection,$emailType): array
  {
    if($emailType == 'minivan') {
      $subjects = $this->minivanSubjects;
    } else {
      $subjects = $this->matchbackSubject;
    }
    $emails = imap_search($connection,'UNSEEN');
    $emailsToProcess = array();
    if($emails) {
      foreach($emails as $emailNumber) {
        $overview = imap_fetch_overview($connection,$emailNumber);
        if($overview[0]->seen) {continue;}
        //nlp_debug_msg('overview', $overview);
        $subject = $overview[0]->subject;
        $date = $overview[0]->date;
        $dateParts = explode(' ',$date);
        $day = $dateParts[1];
        $month = $dateParts[2];
        
        $fileType = NULL;
        foreach ($subjects as $emailSubject => $emailType) {
          $pos = strpos($emailSubject, $subject);
          if($pos !== FALSE) {
            $fileType = $emailType;
            break;
          }
        }
        if(!empty($fileType)) {
          $emailsToProcess[$emailNumber] = array(
            'fileType' => $fileType,
            'day' => $day,
            'month' => $month,
            'subject' => $subject
          );
        }
      }
    }
    return $emailsToProcess;
  }
  
  /**
   * @param $connection
   * @return array
   * @noinspection PhpUnused
   */
  public function getNewNotificationReplies($connection): array
  {
    $emails = imap_search($connection,'UNSEEN');
    //nlp_debug_msg('$emails',$emails);
    $emailsToProcess = [];
    if(!empty($emails)) {
      foreach($emails as $emailNumber) {
        $overview = imap_fetch_overview($connection,$emailNumber);
        if($overview[0]->seen) {continue;}
        $subject = $overview[0]->subject;
        $date = $overview[0]->date;
        $dateParts = explode(' ',$date);
        $day = $dateParts[1];
        $month = $dateParts[2];
        $emailsToProcess[$emailNumber] = [
          'day' => $day,
          'month' => $month,
          'subject' => $subject
        ];
      }
    }
    return $emailsToProcess;
  }
  
  /**
   * @param $connection
   * @param $emailNumber
   * @return array
   */
  public function getNewEmailAttachments($connection,$emailNumber): array
  {
    $structure = imap_fetchstructure($connection,$emailNumber);
    //nlp_debug_msg('structure', (array) $structure->parts );
    $attachments = [];
    if(isset($structure->parts) && count($structure->parts)) {
      for($i = 0; $i < count($structure->parts); $i++) {
        $attachments[$i] = [
          'is_attachment' => false,
          'filename' => '',
          'name' => '',
          'attachment' => '',
        ];
        if($structure->parts[$i]->ifdparameters) {
          $attachment = $this->getAttachment($structure->parts[$i]->dparameters,'filename',$emailNumber);
          if(!empty($attachment)) {
            $attachments[$i] = $attachment;
          }
        }
        if($structure->parts[$i]->ifparameters) {
          //nlp_debug_msg('parameter',$structure->parts[$i]->parameters);
          $attachment = $this->getAttachment($structure->parts[$i]->parameters,'name',$emailNumber);
          if(!empty($attachment)) {
            $attachments[$i] = $attachment;
          }
        }
        //nlp_debug_msg('attachments',$attachments);
        if($attachments[$i]['is_attachment']) {
          $attachments[$i]['attachment'] = imap_fetchbody($connection, $emailNumber, $i+1);
          if($structure->parts[$i]->encoding == 3) { // 3 = BASE64
            $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
          }
          elseif($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
            $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
          }
        }
      }
    }
    return $attachments;
  }
  
  /**
   * @param $connection
   * @param $emailNumber
   * @return string
   */
  public function getHeader($connection,$emailNumber): string
  {
    return imap_fetchheader($connection, $emailNumber);
  }
  
  /** @noinspection PhpUnused */
  /**
   * @param $connection
   * @param $emailNumber
   * @return array|string[]|null
   */
  public function getMsg($connection, $emailNumber): ?array
  {
    $overview = imap_fetch_overview($connection,$emailNumber);
    //nlp_debug_msg('$overview',$overview);
    $subject = $overview[0]->subject;
    //nlp_debug_msg('$subject',$subject);
    $emailObj = imap_fetchstructure($connection,$emailNumber);
    //nlp_debug_msg('$emailObj',$emailObj);
    //$parts = $this->getParts($connection, $emailObj, $emailNumber, 0);
  
    if (!$emailObj->parts)  // simple
      $parts[0] = $this->getpart($connection,$emailNumber,$emailObj,0);  // pass 0 as part-number
    else {  // multipart: cycle through each part
      foreach ($emailObj->parts as $partNo=>$partInfo)
        $parts[$partNo] = $this->getpart($connection,$emailNumber,$partInfo,$partNo+1);
    }
    $parts['subject'] = $subject;
    //nlp_debug_msg('$parts',$parts);
    return $parts;
  }
  
  /**
   * @param $connection
   * @param $messageId
   * @param $partObj
   * @param $partNo - $partNo = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
   * @return array
   */
  private function getPart($connection,$messageId,$partObj,$partNo): array
  {
    //nlp_debug_msg('$partNo',$partNo);
    //nlp_debug_msg('$partObj',$partObj);
    $part = [];
    
    //$plainText = $htmlText = '';
    //$attachments = [];
    // Decode the body.
    $data = !empty(($partNo))?
      imap_fetchbody($connection,$messageId,$partNo):  // multipart
      imap_body($connection,$messageId);  // simple
    // Any part may be encoded, even plain text messages, so check everything.
    if ($partObj->encoding==4)
      $data = quoted_printable_decode($data);
    elseif ($partObj->encoding==3)
      $data = base64_decode($data);
    //nlp_debug_msg('$data',$data);
    
    $type = $partObj->type;
    $part['type'] = (!empty($this->messageType[$type]))?$this->messageType[$type]:$partObj->type;
    $subType = 'UNKNOWN';
    if(!empty($partObj->ifsubtype)) {
      $subType = $part['subtype'] = $partObj->subtype;
    }
    if(!empty($partObj->ifdescription)){
      $part['description'] = $partObj->description;
    }
  
    // Get parameters.
    $params = array();
    if ($partObj->parameters)
      foreach ($partObj->parameters as $x)
        $params[strtolower($x->attribute)] = $x->value;
    if ($partObj->dparameters)
      foreach ($partObj->dparameters as $x)
        $params[strtolower($x->attribute)] = $x->value;
    $part['params'] = $params;
  
    $part['plainText'] = $part['htmlText'] = '';
    $part['attachments'] = [];
    
    // ATTACHMENT
    // Any part with a filename is an attachment,
    // so an attached text file (type 0) is not mistaken as the message.
    if ($params['filename'] || $params['name']) {
      // filename may be given as 'Filename' or 'Name' or both
      $filename = ($params['filename'])? $params['filename'] : $params['name'];
      // filename may be encoded, so see imap_mime_header_decode()
      $part['attachments'][$filename] = $data;  // this is a problem if two files have same name
    }
  
    // TEXT
   // nlp_debug_msg('$type',$type);
    //nlp_debug_msg('$subType',$subType);
    //nlp_debug_msg('TEXT',$this::TEXT);
    if ($type==$this::TEXT AND !empty($data)) {
      // Messages may be split in different parts because of inline attachments,
      // so append parts together with blank row.
      if (strtolower($partObj->subtype)=='plain') {
        $part['plainText'] = trim($data);
        //nlp_debug_msg('$plainText',$plainText);
      }
      else {
        $part['htmlText'] = $data;
        //nlp_debug_msg('$htmlText',$htmlText);
      }
      $charset = $params['charset'];  // assume all parts are same charset
    }
  
    // EMBEDDED MESSAGE
    // Many bounce notifications embed the original message as type 2,
    // but AOL uses type 1 (multipart), which is not handled here.
    // There are no PHP functions to parse embedded messages,
    // so this just appends the raw source to the main message.
    elseif ($partObj->type==$this::PLAIN && !empty($data)) {
      $part['plainText'] = $data;
    }
    //$part['plainText'] = $plainText;
    //$part['htmlText'] = $htmlText;
    //$part['attachments'] = $attachments;
    //nlp_debug_msg('$part',$part);
    $part['partNo'] = $partNo;
  
    // SUBPART RECURSION
    if ($partObj->parts) {
      foreach ($partObj->parts as $subPartNo => $subPartObj) {
        $part['parts'][] = $this->getPart($connection, $messageId, $subPartObj, $partNo . '.' . ($subPartNo + 1));  // 1.2, 1.2.1, etc.
      }
    }
    
    return $part;
  }
  
  /**
   * Return an array containing data from the various message parts.
   *
   * The results look much like this for a single part email, with the
   * message headers in raw and parsed form contained in the first array field.
   *
   * Array (
   *   [0] => Array (
   *     [charset] => us-ascii
   *     [raw] => Return-Path:
   *               Delivered-To: account@domain.com
   *               Reply-To:
   *               From: "That Guy"
   *               To:
   *               Subject: test 1
   *               Date: Mon, 7 Feb 2011 19:37:07 -0800
   *               Message-ID:
   *               MIME-Version: 1.0
   *               Content-Type: text/plain;
   *               charset="us-ascii"
   *               Content-Transfer-Encoding: 7bit
   *               Content-Language: en-us
   *     [data] => Array(
   *       [Return-Path] =>
   *       [Delivered-To] => account@domain.com
   *       [Reply-To] =>
   *       [From] => "That Guy"
   *       [To] =>
   *       [Subject] => test 1
   *       [Date] => Mon, 7 Feb 2011 19:37:07 -0800
   *       [Message-ID] =>
   *       [MIME-Version] => 1.0
   *       [Content-Type] => text/plain;charset="us-ascii"
   *       [Content-Transfer-Encoding] => 7bit
   *     )
   *   )
   *   [1] => Array (
   *     [charset] => us-ascii
   *     [data] => example mail body, probably much longer in reality
   *   )
   * )
   *
   * Multipart mails will contain more array entries of data, one for each
   * part or file attachment.
   *
   * @param $connection
   * @param  $message_number
   *   The message identifier.
   *
   * @return array
   *   The email.
   */
  public function getMessage($connection, $message_number): array
  {
  
    $overview = imap_fetch_overview($connection,$message_number);
    $subject = $overview[0]->subject;
    
    $mail = imap_fetchstructure($connection, $message_number, NULL);
    if (!is_object($mail)) {
      nlp_debug_msg('imap_fetchstructure',t('Failed to retrieve message structure for message number = %id',
        ['%id' => $message_number]));
      return [];
    }
    
    // $this->mailGetParts() will chase down all the components of a
    // multipart mail, but only return the headers of a single part mail.
    $mail = $this->mailGetParts($connection, $message_number, $mail, '0');
    $mail[0]['raw'] = $mail[0]['data'];
    $mail[0]['data'] = $this->parseMailHeadersIntoArray($mail[0]['raw']);
    if (!isset($mail[0]['charset'])) {
      // Probably wrong, but better than nothing.
      $mail[0]['charset'] = 'utf-8';
    }
    
    // So if it is only a single part mail, we have to go and fetch the body.
    if (count($mail) == 1) {
      //$body = $this->imapBody($message_number);
      $body = imap_body($connection, $message_number, NULL);
      if (!is_string($body)) {
        //$this->watchdogLastImapError(t('Failed to retrieve message body for message number = %id', array('%id' => $message_number)));
        nlp_debug_msg('imap_body',t('Failed to retrieve message body for message number = %id',
          ['%id' => $message_number]));
      }
      $mail[] = array(
        'data' => $body,
        'charset' => $mail[0]['charset'],
      );
    }
    $mail['subject'] = $subject;
    return $mail;
  }
  
  /**
   * Return the parts of a multipart mail.
   *
   * Recursively walk through the parts, obtaining the content for each part,
   * and return the whole as nested arrays.
   *
   * @param  $connection
   * @param  $message_number
   *   The identifier for the message.
   * @param  $part
   *   A message part.
   * @param  $prefix
   *   Describing the position in the structure.
   *
   * @return array
   *   The parts of the mail as an array.
   */
  protected function mailGetParts($connection, $message_number, $part, $prefix): array
  {
    $attachments = [];
    $attachments[$prefix] = $this->mailDecodePart($connection,$message_number, $part, $prefix);
    if (isset($part->parts)) {
      // This is multipart.
      $prefix = ($prefix == '0') ? '' : $prefix . '.';
      foreach ($part->parts as $index => $subpart) {
        $attachments = array_merge(
          $attachments,
          // The $index below should be 0-based, but what needs to be passed
          // into the server is 1-based, hence the + 1.
          $this->mailGetParts($connection, $message_number, $subpart, $prefix . ($index + 1))
        );
      }
    }
    return $attachments;
  }
  
  /**
   * @param $connection
   * @param $message_number
   * @param $part
   * @param $prefix
   * @return array
   */
  protected function mailDecodePart($connection,$message_number, $part, $prefix): array
  {
    //nlp_debug_msg('$part',$part);
    $attachment = [];
  
    $type = $part->type;
    $attachment['type'] = (!empty($this->messageType[$type]))?$this->messageType[$type]:$part->type;
    $subType = 'UNKNOWN';
    if(!empty($part->ifsubtype)) {
      $attachment['subtype'] = $part->subtype;
    }
    if(!empty($partObj->ifdescription)){
      $attachment['description'] = $part->description;
    }
    
    
    if (isset($part->ifdparameters) && $part->ifdparameters) {
      foreach ($part->dparameters as $object) {
        $attachment[mb_strtolower($object->attribute)] = $object->value;
        if (mb_strtolower($object->attribute) == 'filename') {
          $attachment['is_attachment'] = TRUE;
          $attachment['filename'] = $object->value;
        }
      }
    }
    
    if (isset($part->ifparameters) && $part->ifparameters) {
      foreach ($part->parameters as $object) {
        $attachment[mb_strtolower($object->attribute)] = $object->value;
        if (mb_strtolower($object->attribute) == 'name') {
          $attachment['is_attachment'] = TRUE;
          $attachment['name'] = $object->value;
        }
      }
    }
  
    
    // If this thing is large, just return a string saying it is large. Large
    // items are generally not what we are looking for when searching for
    // bounce-related information.
    //
    // Note that if it has sub-parts, the byte count should be a sum of subpart
    // byte counts, so ignore it.
    if (!isset($part->parts) && isset($part->bytes) && $part->bytes > $this::MAXMAILPARTSIZEBYTES) {
      $attachment['data'] = t('Mail part too large to consider: @bytes bytes', array('@bytes' => $part->bytes));
      return $attachment;
    }
    
    $data = imap_fetchbody($connection, $message_number, $prefix);
    if (!is_string($data)) {
      nlp_debug_msg(t('Failed to retrieve message structure for message number = %id',
        ['%id' => $message_number]),'');
      $attachment['data'] = '';
    }
    else {
      if (isset($part->encoding)) {
        // 3 = BASE64.
        if ($part->encoding == 3) {
          $data = base64_decode($data);
        }
        // 4 = QUOTED-PRINTABLE.
        elseif ($part->encoding == 4) {
          $data = quoted_printable_decode($data);
        }
      }
      $attachment['data'] = $data;
    }
    return $attachment;
  }
  
  /**
   * Parse a message header into an associative array of name-value pairs.
   *
   * @param  $headers
   *   The email headers as a string.
   *
   * @return array
   *   An associated array of name-value pairs.
   */
  protected function parseMailHeadersIntoArray($headers): array
  {
    $headers = preg_replace('/\r\n\s+/m', '', $headers);
    preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)?\r\n/m', $headers, $matches);
    $result = array();
    foreach ($matches[1] as $key => $value) {
      $result[$value] = $matches[2][$key];
    }
    return $result;
  }
  
}
