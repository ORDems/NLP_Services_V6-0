<?php

namespace Drupal\nlpservices;

use Drupal;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class ApiVoter
{
  
  protected ClientInterface $client;
  
  public function __construct($client) {
    $this->client = $client;
  }
  
  public static function create(ContainerInterface $container): ApiVoter
  {
    return new static(
      $container->get('http_client'),
    );
  }

  public function decodeApiVoterHdr($columnHeader,$activistCode): array
  {
    $fieldPos = array();
    foreach ($columnHeader as $column=>$fileColName) {
      if($fileColName == 'VanID') {
        $fieldPos['vanid'] = $column;
        break;
      }
    }
    if(!isset($fieldPos['vanid'])) {return [];}
    $activistCodeHdr = 'ActivistCode_'.$activistCode['activistCodeId'];
    foreach ($columnHeader as $column=>$fileColName) {
      if($fileColName == $activistCodeHdr) {
        $fieldPos[$activistCode['nlpIndex']] = $column;
        break;
      }
    }
    return $fieldPos;
  }
  
  public function decodeApiVoterRecord($voterRecord,$fieldPos): array
  {
    $voter = array();
    foreach ($fieldPos as $field => $pos) {
      if($field == 'vanid') {
        $voter['vanid'] = $voterRecord[$pos];
      } else {
        $voter['activistCodes'][$field] = $voterRecord[$pos];
      }
    }
    return $voter;
  }
  
  private function parseAddress($address): array
  {
    $addressFields = array();
    $streetParts = array();
    $prefixTypes = array('E','N','NE','NW','S','SE','SW','W');
    /** @noinspection SpellCheckingInspection */
    $streetTypes = array('Aly','Ave','Bch','Blvd','Byp','Cir','Crst','Ct','Dr',
      'Ext','Grns','Hts','Hwy','Lk','Ln','Lndg','Loop','Park','Path','Pkwy',
      'Pl','Plz','Pt','Rd','Rdg','Sq','St','Ter','Trce','Trl','Walk','Way',
      'Brg','Ctr','Groun','Pne','Run','Vlg','Vw','Clb','Cor','Crk','Ests',
      'Expy','Flds','Flts','Frst','Fry','Gln','Grn','Hl','Hls','Holw','Isle',
      'Jct','Knl','Mdws','Mews','Ml','Mnr','Pass','Pnes','Riv','Rnch','Row',
      'Rst','Spg','Spur','Trak','Vis','Xing');
    /** @noinspection SpellCheckingInspection */
    $aptTypes = array('#','Apt','Bldg','Bsmt','Condo','Dept','Fl','Frnt',
      'Hillsb','Lot','Lowr','Map','McMinn','Near','Ofc','Ph','Pier','Portla',
      'Rear','Rm','Side','Slip','Spc','Ste','Tigard','Trlr','Unit','Uppr',
      '& Hywy','Area','Ashlan','Attn','Baker ','Behind','Blk','Block',
      'Cut Of','Grade','Hngr','Horsec','Joseph','La Gra','Lafaye','Lbby',
      'Lithia','Lots','Madras','Medfor','Mile','Mile M','Mile P','Miles',
      'Near M','No','Off','Over','Parkda','Range','Schoon','Stop','Townsh',
      'Ms','Fairvi','Up Meh','Ship','Troutd','Sandy','Tyson','Msc','Schoen',
      'Shipst','Mcs','Lund F','Mehlin','Corrad','Kenna','Waterf','Estaca',
      'Reed C','Gresha','Studio','& Hals','Around','Mscn','Villa ','At Or ',
      'Kenna ','Christ','Albany','Brooki','Dock','Drain','Eugene','In Car',
      'Langlo','Loft','On Mai','Oregon','Rosebu','Scio','Spring','Tax',
      'Taxlot','Winche','Winsto','Box','Canby','Gervai','Linn O','Milwau',
      'Molall','Near S','Newpor','Otis','Rsc','Salem','Salem ','Sherid',
      'Stayto');
    $fields = explode(' ', $address);
    // At the beginning, look for the street number and prefix
    $partIndex = 0;
    $addressFields['streetNo'] = NULL;
    if(is_numeric($fields[$partIndex])) {
      $addressFields['streetNo'] = $fields[$partIndex];
      $partIndex++;
    }
    $addressFields['streetPrefix'] = NULL;
    if(in_array($fields[$partIndex], $prefixTypes)) {
      $addressFields['streetPrefix'] = $fields[$partIndex];
      $partIndex++;
    }
    // At the end look for the apartment type and number.
    $partEnd = count($fields)-1;
    $addressFields['aptNo'] = $addressFields['aptType'] = NULL;
    if(($partEnd-$partIndex)>2) {
      if(in_array($fields[$partEnd], $aptTypes)) {
        $addressFields['aptType'] = $fields[$partEnd];
        $partEnd--;
      } elseif (in_array($fields[$partEnd-1], $aptTypes)) {
        $addressFields['aptType'] = $fields[$partEnd-1];
        $addressFields['aptNo'] = $fields[$partEnd];
        $partEnd = $partEnd-2;
      }
    }
    
    $addressFields['streetSuffix'] = NULL;
    if($partEnd-$partIndex>0) {
      if(in_array($fields[$partEnd], $prefixTypes)) {
        $addressFields['streetSuffix'] = $fields[$partEnd];
        $partEnd--;
      }
    }
    
    $addressFields['streetType'] = NULL;
    if($partEnd-$partIndex>0) {
      if(in_array($fields[$partEnd], $streetTypes)) {
        $addressFields['streetType'] = $fields[$partEnd];
        $partEnd--;
      }
    }
    for ($index = $partIndex; $index <= $partEnd; $index++) {
      $streetParts[] = $fields[$index];
    }
    $streetName = implode(' ', $streetParts);
    $addressFields['streetName'] = $streetName;
    return $addressFields;
  }
  
  public function getApiVoter($committeeKey,$database,$vanid): array
  {
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    
    $expandOptions = '?$expand=phones,emails,addresses,codes,districts,electionRecords';
    $url = 'https://'.$user.':'.$apiKey.'|'.$database.
      '@'.$apiURL.'/people/'.$vanid.$expandOptions;
    //nlp_debug_msg('$post_url',$post_url);
    try {
      $request = $this->client->get($url);
      $result = json_decode($request->getBody(), true);
      //nlp_debug_msg('$result',$result);
    } catch (Exception $e) {
      //$messenger->addStatus( $this->t('An error occurred. Please contact the Administrator.'));
      nlp_debug_msg('Error message',$e->getMessage());
      return [];
    }

    $voterAddress = $voter = array();
    $voter['vanid'] = $result['vanId'];
    $voter['firstName'] = $result['firstName'];
    $voter['lastName'] = $result['lastName'];
    $voter['nickname'] = $result['nickname'];
    $dateOfBirth = $result['dateOfBirth'];
    $birthDay = explode("T", $dateOfBirth);
    $birthDate = explode("-", $birthDay[0]);
    $birthDate['year'] = $birthDate[0];
    $birthDate['month'] = $birthDate[1];
    $birthDate['day'] = $birthDate[2];
    // get age from date or birthdate
    $birthTime = mktime(0, 0, 0, $birthDate['month'], $birthDate['day'], $birthDate['year']);
    $birthUDate = date("U", $birthTime);
    $age = (date("md", $birthUDate) > date("md")
      ? ((date("Y") - $birthDate['year']) - 1)
      : (date("Y") - $birthDate['year']));
    $voter['age'] = $age;
    $voter['party'] = $result['party'];
    $voter['sex'] = $result['sex'];
    $voterAddress['city'] = $voterAddress['streetName'] = $voterAddress['streetPrefix'] =  $voterAddress['streetNo'] = NULL;
    $voterAddress['mZip'] = $voterAddress['mCity'] = $voterAddress['mAddress'] = NULL;
    if(!empty($result['addresses'])) {
      foreach ($result['addresses'] as $address) {
        $type = $address['type'];
        switch ($type) {
          case 'Voting':
            $address1 = $address['addressLine1'];
            $addressFields = $this->parseAddress($address1);
            foreach ($addressFields as $addressKey => $addressValue) {
              $voterAddress[$addressKey] = $addressValue;
            }
            $voterAddress['city'] = $address['city'];
            break;
          case 'Mailing':
            $voterAddress['mAddress'] = $address['addressLine1'];
            $voterAddress['mCity'] = $address['city'];
            $voterAddress['mState'] = $address['stateOrProvince'];
            $voterAddress['mZip'] = $address['zipOrPostalCode'];
            break;
        }
      }
    }
    
    $voter['homePhone'] = $voter['cellPhone'] = $voter['preferredPhoneType'] = NULL;
    if(!empty($result['phones'])) {
      //nlp_debug_msg('phones',$result['phones']);
      foreach ($result['phones'] as $phone) {
        $phoneType = $phone['phoneType'];
        switch ($phoneType) {
          case 'Home':
            if($phone['isPreferred']) {
              $voter['homePhone'] = $phone['phoneNumber'];
              $voter['homePhoneId'] = $phone['phoneId'];
              $voter['preferredPhoneType'] = 'H';
            } else {
              if(empty($voter['homePhone'])) {
                $voter['homePhone'] = $phone['phoneNumber'];
                $voter['homePhoneId'] = $phone['phoneId'];
              }
            }
            break;
          case 'Cell':
            if($phone['isPreferred']) {
              $voter['cellPhone'] = $phone['phoneNumber'];
              $voter['cellPhoneId'] = $phone['phoneId'];
              $voter['smsOptInStatus'] = $phone['smsOptInStatus'];
              $voter['preferredPhoneType'] = 'C';
            } else {
              if(empty($voter['cellPhone'])) {
                $voter['cellPhone'] = $phone['phoneNumber'];
                $voter['cellPhoneId'] = $phone['phoneId'];
                $voter['smsOptInStatus'] = $phone['smsOptInStatus'];
              }
            }
            break;
        }
      }
    }
    
    $voterAddress['precinct'] = $voterAddress['hd'] = $voterAddress['county'] = $voterAddress['cd'] = NULL;
    if(!empty($result['districts'])) {
      foreach ($result['districts']as $district) {
        $districtType = $district['name'];
        switch ($districtType) {
          case 'Congressional':
            $field = $district['districtFieldValues'][0];
            $voterAddress['cd'] = $field['name'];
            break;
          case 'county':
            $field = $district['districtFieldValues'][0];
            $voterAddress['county'] = $field['name'];
            break;
          case 'Precinct':
            $field = $district['districtFieldValues'][0];
            $voterAddress['precinct'] = $field['name'];
            break;
          case 'State House':
            $field = $district['districtFieldValues'][0];
            $voterAddress['hd'] = $field['name'];
            break;
        }
      }
    }
    
    $voter['address'] = $voterAddress;
    
    //$voter['votingHistory'] = NULL;
    $voting = '';
    if(!empty($result['electionRecords'])) {
      $electionArrays =  $this->findVotingRecord($result['electionRecords']);
      foreach ($electionArrays['mostRecent'] as $election) {
        $voting .= $election['type'].$election['year'].':'.$election['participation'].' ';
      }
      if(!empty($electionArrays['local'])) {
        $voting .= '<br>'.$electionArrays['local'];
      }
    }
    $voter['votingHistory'] = $voting;
    
    return $voter;
  }


  private function findVotingRecord($electionRecords): array
  {
    //nlp_debug_msg('$electionRecords',$electionRecords);
    $config = Drupal::service('config.factory')->get('nlpservices.configuration');
    $electionDates = $config->get('nlpservices-election-configuration');
    $cycle = $electionDates['nlp_election_cycle'];
    $local = $electionDates['nlp_local_election'];

    if(!empty($local)) {
      list($localYear,$localType) = explode('-',$local);
      $localYear = trim($localYear);
      $localType = trim($localType);
    }
    list($year, ,$type) = explode('-',$cycle);
    // For a general election in an even year, look back starting with the primary else look back starting with the
    // previous general election.
    $general = FALSE;
    if($year % 2 == 0){
      if($type != 'G') {
        $year = $year-2;
        $general = TRUE;
      }
    } else {
      $general = TRUE;
      $year--;
    }
    $twoDigitYear = substr($year,-2);
    $electionArrays = [];

    if($general) {
      $electionArrays[$year . '1'] = ['type' => 'G', 'year' => $twoDigitYear, 'participation' => '@N'];
    }
    $electionArrays[$year.'0'] = ['type'=>'P','year'=>$twoDigitYear,'participation'=>'@N'];
    $year = $year-2;
    $electionArrays[$year.'1'] = ['type'=>'G','year'=>$twoDigitYear-2,'participation'=>'@N'];
    $electionArrays[$year.'0'] = ['type'=>'P','year'=>$twoDigitYear-2,'participation'=>'@N'];
    if(!$general) {
      $year = $year-2;
      $electionArrays[$year.'1'] = ['type'=>'G','year'=>$twoDigitYear-4,'participation'=>'@N'];
    }
    //nlp_debug_msg('$electionArrays',$electionArrays);
    //nlp_debug_msg('$electionRecords',$electionRecords);
    $localParticipation = '@N';
    $mostRecent = array();
    foreach ($electionRecords as $election) {
      //nlp_debug_msg('election object', $electionObj);
      $electionRecordType = $election['electionRecordType'];
      list($year,$electionType) = explode('-', $electionRecordType);
      $year = trim($year);
      $electionType = trim($electionType);
      if($year < 2008) {continue;}  // Too old.
      $type = substr($electionType,0,1);
      $twoDigitYear = substr($year,-2);
      if($type == "G" OR $type == "P") {
        $electionTypeCode = ($type == "G")?1:0;
        $electionIndex = $year.$electionTypeCode;
        $electionArrays[$electionIndex]['type'] = $type;
        $electionArrays[$electionIndex]['year'] = $twoDigitYear;
        //$electionArrays[$electionIndex]['participation'] = (!empty($electionObj->participation))?'@Y':'@N';
        $electionArrays[$electionIndex]['participation'] = ($election['participation'] == "Y")?'@Y':'@N';
      }
      //nlp_debug_msg('$year: '.$year.' $electionType: '.$electionType);
      if(!empty($local) AND $year==$localYear AND $electionType==$localType) {
        $localParticipation = '@Y';
      }
    }
    krsort($electionArrays);
    //nlp_debug_msg('$electionArrays',$electionArrays);
    if(empty($electionArrays)) {return array();}
    $yearCount = 0;
    foreach($electionArrays as $electionIndex => $electionArray) {
      $mostRecent[$electionIndex] = $electionArray;
      if ($yearCount++ == 3) {break;}
    }
    //nlp_debug_msg('$mostRecent',$mostRecent);
    if(!empty($local)) {
      $local .= ': '.$localParticipation;
    }
    //nlp_debug_msg('$local',$local);
    return array('mostRecent'=>$mostRecent,'local'=>$local);
    
  }
}