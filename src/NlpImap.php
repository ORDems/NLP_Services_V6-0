<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
//use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NlpImap
{

  public array $minivanSubjects = array(
    'nlpresponsesexport' => 'survey' ,
    'nlpcontactsexport' => 'canvass',
    'nlpactivistcodesexport' => 'activist',
    'nlpnotesexport' => 'note',
  );

  public array $matchbackSubject = array(
    'matchback' => 'matchback',
  );

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


  public function getImapConnection($email)
  {
    $messenger = Drupal::messenger();

    //$server = $port = $username = $password = '';

    //$nlpConfig = Drupal::service('config.factory')->get('nlpservices.configuration');


    $nlpConfig = $this->config->get('nlpservices.configuration');
    $emailConfiguration = $nlpConfig->get('nlpservices-email-configuration');

    $emailInfo = $emailConfiguration[$email];

    $hostname = '{'.$emailInfo['server'].':'.$emailInfo['port'].'/imap/ssl/novalidate-cert}INBOX';
    //nlp_debug_msg('$emailInfo',$emailInfo);
    $connection = imap_open($hostname,$emailInfo['email'],$emailInfo['password']);
    if(empty($connection)) {
      $error = imap_last_error();
      $messenger->addError('connection: '. $error);
      nlp_debug_msg('$error',$error);
      return NULL;
    }
    return $connection;
  }

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

  /** @noinspection PhpUnused */
  public function getNewNotificationReplies($connection): array
  {
    $emails = imap_search($connection,'UNSEEN');
    //nlp_debug_msg('$emails',$emails);
    $emailsToProcess = array();
    if($emails) {
      foreach($emails as $emailNumber) {
        $overview = imap_fetch_overview($connection,$emailNumber);
        if($overview[0]->seen) {continue;}
        $subject = $overview[0]->subject;
        $date = $overview[0]->date;
        $dateParts = explode(' ',$date);
        $day = $dateParts[1];
        $month = $dateParts[2];

        $emailsToProcess[$emailNumber] = array(
          'day' => $day,
          'month' => $month,
          'subject' => $subject
        );

      }
    }
    return $emailsToProcess;
  }

  public function getNewEmailAttachments($connection,$emailNumber): array
  {
    $structure = imap_fetchstructure($connection,$emailNumber);
    //nlp_debug_msg('structure', (array) $structure->parts );
    $attachments = array();
    if(isset($structure->parts) && count($structure->parts)) {
      for($i = 0; $i < count($structure->parts); $i++) {
        $attachments[$i] = array(
          'is_attachment' => false,
          'filename' => '',
          'name' => '',
          'attachment' => '',
        );
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

  public function getHeader($connection,$emailNumber): string
  {
    return imap_fetchheader($connection, $emailNumber);
  }

  /** @noinspection PhpUnused */
  public function getMsg($connection, $emailNumber): ?array
  {
    $parts = array('htmlmsg'=>'','charset'=>'');
    $overview = imap_fetch_overview($connection,$emailNumber);
    $subject = $overview[0]->subject;
    // BODY
    $s = imap_fetchstructure($connection,$emailNumber);
    if(empty($s->parts)) {return NULL;}
    if (!$s->parts) { // simple
      $parts = $this->getPart($connection,$emailNumber,$s,0);  // pass 0 as part-number
    }
    else {  // multipart: cycle through each part
      foreach ($s->parts as $partNo0=>$p) {
        $multiParts = $this->getPart($connection,$emailNumber,$p,$partNo0+1);
        $parts['charset'] = $multiParts['charset'];
        $parts['htmlmsg'] .= $multiParts['htmlmsg'];
      }
    }
    $parts['subject'] = $subject;
    return $parts;
  }

  private function getPart($mbox,$mid,$p,$partNo): array
  {
    $htmlmsg = '';
    // DECODE DATA
    $data = ($partNo)?
      imap_fetchbody($mbox,$mid,$partNo):  // multipart
      imap_body($mbox,$mid);  // simple
    // Any part may be encoded, even plain text messages, so check everything.
    if ($p->encoding==4) {
      $data = quoted_printable_decode($data);
    } elseif ($p->encoding==3) {
      $data = base64_decode($data);
    }
    // PARAMETERS
    // get all parameters, like charset, filenames of attachments, etc.
    $params = array();
    if ($p->ifparameters) {
      foreach ($p->parameters as $x) {
        $params[strtolower($x->attribute)] = $x->value;
      }
    }
    if ($p->ifdparameters) {
      foreach ($p->dparameters as $x) {
        $params[strtolower($x->attribute)] = $x->value;
      }
    }

    // TEXT
    $charset = NULL;
    if ($p->type==0 && $data) {
      // Messages may be split in different parts because of inline attachments,
      // so append parts together with blank row.
      if (strtolower($p->subtype)=='html') {
        $htmlmsg = $data ."<br><br>";
      }
      $charset = $params['charset'];  // assume all parts are same charset
    }
    return array('htmlmsg'=>$htmlmsg,'charset'=>$charset);
  }

}
