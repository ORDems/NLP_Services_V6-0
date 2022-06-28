<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\nlpservices\NlpCoordinators;
//use Drupal\nlpservices\NlpNls;
//use Drupal\nlpservices\NlpVoters;
//use Drupal\nlpservices\NlpTurfs;
//use Drupal\nlpservices\NlpPaths;
//use Drupal\nlpservices\NlpInstructions;
//use Drupal\nlpservices\NlpSessionData;

/**
 * @noinspection PhpUnused
 */
class NlpDataEntryPrint
{
  protected PrivateTempStoreFactory $tempStore;
  protected NlpCoordinators $coordinators;
  protected NlpSessionData $sessionData;
  protected NlpNls $nlsObj;
  protected NlpVoters $votersObj;
  protected NlpTurfs $turfsObj;
  protected NlpPaths $pathsObj;
  protected NlpInstructions $instructionsObj;
  
  
  public function __construct($tempStore,$nlsObj,$sessionData,$coordinators,
                              $votersObj, $turfsObj, $pathsObj, $instructionsObj) {
    $this->tempStore = $tempStore;
    $this->nlsObj = $nlsObj;
    $this->sessionData = $sessionData;
    $this->coordinators = $coordinators;
    $this->votersObj = $votersObj;
    $this->turfsObj = $turfsObj;
    $this->pathsObj = $pathsObj;
    $this->instructionsObj = $instructionsObj;
  
  }
  
  public static function create(ContainerInterface $container): NlpDataEntryPrint
  {
    return new static(
      $container->get('tempstore.private'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.session_data'),
      $container->get('nlpservices.coordinators'),
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.turfs'),
      $container->get('nlpservices.paths'),
      $container->get('nlpservices.instructions'),
    );
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * dataEntryPrint
   *
   * @return string
   * @noinspection PhpUnused
   */
  public function dataEntryPrint(): string
  {
    $messenger = Drupal::messenger();
  
    $session = $this->sessionData->getUserSession();
    //nlp_debug_msg('$session',$session);
    $mcid = $session['mcid'];
    $county = $session['county'];
  
    $instructions = $this->instructionsObj->getInstructions($county);
    $page = '<p>The links below provide access to printable documents for voter contact instructions, canvassing
walk sheet, mailing postcards, and GOTV calling for voters whose ballot is not yet received by county election 
division.  The "Instructions for NL" and walk sheet are PDFs that can be downloaded and printed from your computer.   
The postcard address list and the GOTV call list are pages that you can print using your browser.  (Some links may not
be provided and are not shown. </p>';
    $path = $this->pathsObj->getPath('INST',$county);
    $canvass = $instructions['canvass']['fileName'];
    if(!empty($canvass)) {
      //$canvassUrl = file_create_url($path . $instructions['canvass']['fileName']);
      $canvassUrl = Drupal::service('file_url_generator')->generateAbsoluteString($path . $instructions['canvass']['fileName']);
      $page .= 'Instructions for NL: <a href="' . $canvassUrl . '" target="_blank">Click here</a> ';
    }
  
    $postcard = $instructions['postcard']['fileName'];
    if(!empty($postcard)) {
      //$postcardUrl = file_create_url($path . $instructions['postcard']['fileName']);
      $postcardUrl = Drupal::service('file_url_generator')->generateAbsoluteString($path . $instructions['postcard']['fileName']);
      $page .= '<br>Instructions for postcards: <a href="' . $postcardUrl . '" target="_blank">Click here</a> ';
    }
    
    if(!empty($session['turfIndex'])) {
      $turfIndex = $session['turfIndex'];
    } else {
      $turfArray = $this->turfsObj->turfExists($mcid,$county);
      //nlp_debug_msg('$turfArray',$turfArray);
      if (empty($turfArray)) {
        $messenger->addWarning("You do not have a turf assigned");
        return '';
      }
      $turfIndex = $turfArray['turfIndex'];
    }
    //nlp_debug_msg('$turfIndex',$turfIndex);
    $turf = $this->turfsObj->getTurf($turfIndex);
    //nlp_debug_msg('$turf',$turf);
    $turfPDF = $turf['turfPDF'];
    if(!empty($turfPDF)) {
      $pdfPath = $this->pathsObj->getPath('PDF',$county);
      $pdfUri = $pdfPath . $turfPDF;
      //$url = file_create_url($pdfUri);
      $url = Drupal::service('file_url_generator')->generateAbsoluteString($pdfUri);
      $page .=  '<br><span style="font-weight:bold; color: blue;">Get your walk sheet: </span><a href="'.$url.
        '" target="_blank"> Click Here</a>';
    }

    $link = '<a href=nlp-printable-calling-page/'.$turfIndex.'>Calling list.</a>';
    $link2 = '<a href=nlp-printable-mailing-page/'.$turfIndex.'>Mailing list.</a>';
    $page .= '<br>'.$link.'<br>'.$link2;
    //nlp_debug_msg('$page',$page);
    return $page;
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * getVoters
   * 
   * @param $turfIndex
   * @return array
   * @noinspection PhpUnused
   */
  public function getVoters($turfIndex): array
  {
    $voters = $this->votersObj->fetchVotersByTurf($turfIndex);
    foreach ($voters as $vanid=>$voter) {
      $voterStatus = $this->votersObj->getVoterStatus($vanid);
      $voters[$vanid]['status'] = $voterStatus;
    }
    return $voters;
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * buildCallList
   * 
   * @param $voters
   * @return string
   * @noinspection PhpUnused
   */
  public function buildCallList($voters): string
  {
    // Get the list of voters for this turf, order by voting address.
    $page = "<h1>GOTV Call list</h1>
<p>The following table contains the list of households from your
turf.  The names in red are those that have not yet <br>voted (also marked with
an asterisks). The column on the right indicates that at least one voter in the
household <br>has not yet voted and a reminder call might be helpful.</p>";
    $page .= '<table class="call-table">';
    // Create the display of voter's, grouped BY ADDRESS if more than one at the same address.
    $someOneToCall = FALSE;
    $savedHousehold = array();
    foreach ($voters as $voter) {
      $exclude = ($voter['status']['deceased'] OR $voter['status']['hostile']);
      // Extracted the name, address and age info from the vtr record.
      $voterNickname = " [".$voter['nickname']."]";
      $voterName = $voter['firstName']." ".$voter['lastName'];
      $exclusion = '';
      if($exclude) {
        $voterName = '<del>'.$voterName.'</del>';
        $exclusion = ($voter['status']['deceased'])?'DECEASED':'HOSTILE';
      }
      $voterAge = " - Age(".$voter['age'].")";
      $voterSex = " ".$voter['sex'];
      $status = $voter['status'];
      //nlp_debug_msg('status', $status);
      if(empty($status['voted']) AND !$exclude) {
        $householdNames = '<span class="help-not-voted">'
          .$voterName.$voterNickname.'</span>'.$voterSex.$voterAge.' *';
      } else {
        $householdNames = $voterName.$voterNickname.$voterSex.$voterAge.' '.$exclusion;
      }
      if(empty($voter['homePhone']) AND empty($voter['cellPhone'])) {
        $householdNames .= '<br>&nbsp;&nbsp;<i>No phone number</i>';
      } else {
        if(empty($status['voted'])) {
          // This voter hasn't voted yet and there is a phone number.
          $someOneToCall = TRUE;
        }
        $householdNames .= '<br>&nbsp;&nbsp;';
        if(!empty($voter['homePhone'])) {
          $householdNames .= "H:".$this->formatTelephone($voter['homePhone']).' ';
        }
        if(!empty($voter['cellPhone'])) {
          $householdNames .= "C:".$this->formatTelephone($voter['cellPhone']);
          $optIn = (!empty($voter['smsOptInStatus']))?$voter['smsOptInStatus']:'unknown';
          $householdNames .= '<span class="voter-sms-status" > SMS Text Status: '.$optIn.'</span>';
        }
      }
      $voterAddress = $voter['address'];
      $householdAddress = $voterAddress['streetNo'].' '
        .$voterAddress['streetPrefix'].' '.$voterAddress['streetName'].' '.$voterAddress['streetType'];
      if(!empty($voterAddress['aptType']) OR !empty($voterAddress['aptNo'])) {
        $householdAddress .= '<br>'.$voterAddress['aptType'].' '.$voterAddress['aptNo'];
      }
      $householdAddress .= '<br>'.$voterAddress['city'];
      // If the first voter in household, remember name and address in case
      // there are others.
      if (empty($savedHousehold['address'])) {
        $savedHousehold['address'] = $householdAddress;
        $savedHousehold['names'] = $householdNames;
        $savedHousehold['someOneToCall'] = $someOneToCall;
      } else {
        // If not the first voter in the household, then if another voter at the
        // same address, then add the name to the list.
        if($householdAddress == $savedHousehold['address']) {
          $savedHousehold['names'] .= "<br>".$householdNames;
          if(!$savedHousehold['someOneToCall']) {
            $savedHousehold['someOneToCall'] = $someOneToCall;
          }
        } else {
          // If this voter is registered at a different address, write the
          // household address record, and start over with this voter.
          
          $page .= "<tr>";
          $page .= '<td class="name">'.$savedHousehold['names'].'</td>';
          $page .= '<td class="address">'.$savedHousehold['address'].'</td>';
          $call = ($savedHousehold['someOneToCall'])?'<span class="help-call">call</span>':'';
          $page .= '<td class="call-status">'.$call.'</td>';
          $page .= "</tr>";
          
          $savedHousehold['address'] = $householdAddress;
          $savedHousehold['names'] = $householdNames;
          $savedHousehold['someOneToCall'] = $someOneToCall;
        }
      }
      $someOneToCall = FALSE;
    }
    // Write the record for the last household.
    if (!empty($savedHousehold['address'])) {
     
      $page .= "<tr>";
      $page .= '<td class="name">'.$savedHousehold['names'].'</td>';
      $page .= '<td class="address">'.$savedHousehold['address'].'</td>';
      $call = ($savedHousehold['someOneToCall'])?'<span class="help-call">call</span>':'';
      $page .= '<td class="call-status">'.$call.'</td>';
      $page .= "</tr>";
    }
  
    $page .=  "</table>";
  
    $page .=  "<p> * Hasn't voted yet.</p>";
    return $page;
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * formatTelephone
   * 
   * @param $phone_number
   * @return string
   */
  function formatTelephone($phone_number): string
  {
    if(empty($phone_number)) {return'';}
    $cleaned = preg_replace('/[^[:digit:]]/', '', $phone_number);
    $matches = [];
    preg_match('/(\d{3})(\d{3})(\d{4})/', $cleaned, $matches);
    return "($matches[1]) $matches[2]-$matches[3]";
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * buildMailingList
   *
   * Create a mailing address list for each household.   The names and ages of
   * every voter in the household will be listed to help address the postcard.
   *
   * @param $voters
   * @return string - File name where mail list is saved.
   */
  function buildMailingList($voters): string
  {
    $page = "<h1>Postcard mailing address list</h1>";
    $page .=  '<table class="mail-table">';

    $page .= '<thead><tr>';

    $page .= '<th class="name">Name(s)</th><th class="address">Mailing Address</th>';
    $page .=  '</tr></thead>';
    $page .=  '<tbody>';

    // Create the display of voter's mailing address, grouped if more than one at the same address.
    foreach ($voters as $voter) {
      $exclude = ($voter['status']['deceased'] OR $voter['status']['hostile']);
      // Extracted the name, address and age info from the vtr record.
      $salutation = " [".$voter['nickname']."]";
      $name = $voter['firstName']." ".$voter['lastName'];
      $exclusion = '';
      if($exclude) {
        $name = '<del>'.$name.'</del>';
        $exclusion = ($voter['status']['deceased'])?'DECEASED':'HOSTILE';
      }
      $age = "- Age(".$voter['age'].")";
      $voterName = $name.$salutation.$age;
      if($exclude) {
        $voterName .= ' '.$exclusion;
      }
      if(empty($voter['address']['mAddress'])) {
        $mailingAddress = 'Not available.';
      } else {
        $mailingAddress = $voter['address']['mAddress'].'<br>'.$voter['address']['mCity']. ', '.$voter['address']['mState'].' '.$voter['address']['mZip'];
      }
      //nlp_debug_msg('$mailingAddress',$mailingAddress);
      // If the first voter in household, remember name and address in case there are others.
      if (empty($current['address'])) {
        $current['address'] = $mailingAddress;
        $current['name'] = $voterName;
      } else {
        // If not the first voter in the household, then if another voter at the
        // same address, then add the name to the list.
        if($mailingAddress == $current['address']) {
          $current['name'] .= "<br>".$voterName;
        } else {
          // If this voter is registered at a different address, write the
          // mailing address record, and start over with this voter.
  
          $page .= "<tr>";
          $page .= '<td class="name">'.$current['name']."</td>";
          $page .= '<td class="address">'.$current['address']."</td>";
          $page .= "</tr>";
          
          $current['address'] = $mailingAddress;
          $current['name'] = $voterName;
        }
      }
    }
    // Write the record for the last household.
    if (!empty($current['address'])) {
      $page .= "<tr>";
      $page .= '<td class="name">'.$current['name']."</td>";
      $page .= '<td class="address">'.$current['address']."</td>";
      $page .= "</tr>";
    }
  
    $page .=  "</tbody></table>";
    return $page;
  }
}
