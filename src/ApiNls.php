<?php

namespace Drupal\nlpservices;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;
use GuzzleHttp\ClientInterface;

/**
 * @noinspection PhpUnused
 */
class ApiNls {

  public array $phoneTypesExt = array(
    'Cell' => 'C',
    'Home' => 'H',
    'Other' => 'O',
    'Main' => 'M',
    'Work' => 'W',
  );
  
  public array $phoneTypes = array(
    'cell' => 'Cell',
    'home' => 'Home',
    'other' => 'Other',
    'main' => 'Main',
    'work' => 'Work',
  );
  
  public array $emailTypes = array(
    'personal' => 'P',
    'work' => 'W',
    'other' => 'O',
  );
  
  protected ClientInterface $client;
  
  public function __construct($client) {
    $this->client = $client;
  }
  
  public static function create(ContainerInterface $container): ApiNls
  {
    return new static(
      $container->get('http_client'),
    );
  }
  
  private function nlApiCall($committeeKey,$mcid,$expandOptions,$errorStatus=FALSE)
  {
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    
    $post_url = 'https://'.$user.':'.$apiKey.'|1'.'@'.$apiURL.'/people/'.$mcid.$expandOptions;
    //nlp_debug_msg('$post_url',$post_url);
    try {
      $request = $this->client->get($post_url);
      $results = json_decode($request->getBody(), true);
      //nlp_debug_msg('$results',$results);
    } catch (Exception $e) {
      //$messenger->addStatus( $this->t('An error occurred. Please contact the Administrator.'));
      $code = $e->getCode();
      //nlp_debug_msg('$code',$code);
      if($code == 404) {return [];}
      if($errorStatus AND $code==401) {
        return ['Invalid key'];
      }
      nlp_debug_msg('Error message',$e->getMessage());
      return [];
    }
    return $results;
  }

  /** @noinspection PhpUnused */
  public function getApiNls($committeeKey, $mcid, $errorStatus=FALSE): array
  {
    $expandOptions = '?$expand=phones,emails,addresses,districts,preferences';
    $result = $this->nlApiCall($committeeKey,$mcid,$expandOptions, $errorStatus);
    //nlp_debug_msg('$result',$result);
    if(empty($result)) {return [];}
    if(!empty($result[0]) AND $result[0] == 'Invalid key') {return $result;};
    $nl = array();
    $nl['mcid'] = $result['vanId'];
    $nl['firstName'] = $result['firstName'];
    $nl['lastName'] = $result['lastName'];
    $nl['nickname'] = $result['nickname'];
    $nl['city'] = $nl['address'] = NULL;
    if(!empty($result['addresses'])) {
      foreach ($result['addresses'] as $address) {
        $type = $address['type'];
        if($type == 'Voting') {
          $nl['address'] = $address['addressLine1'];
          $nl['city'] = $address['city'];
        }
      }
    }
    $nl['homePhone'] = $nl['cellPhone'] = $nl['phone'] = NULL;
    if(!empty($result['phones'])) {
      foreach ($result['phones'] as $phone) {
        $phoneType = $phone['phoneType'];
        switch ($phoneType) {
          case 'Home':
            $nl['homePhone'] = $phone['phoneNumber'];
            $nl['homePhoneId'] = $phone['phoneId'];
            break;
          case 'Cell':
            $nl['cellPhone'] = $phone['phoneNumber'];
            $nl['cellPhoneId'] = $phone['phoneId'];
            break;
        }
        if($phone['isPreferred']) {
          $nl['phone'] = $phone['phoneNumber'];
        }
      }
    }
    $nl['email'] = NULL;
    if(!empty($result['emails'])) {
      foreach ($result['emails'] as $email) {
        if($email['isPreferred']) {
          $nl['email'] = $email['email'];
        }
      }
    }
    $nl['precinct'] = $nl['hd']= NULL;
    if(!empty($result['districts'])) {
      foreach ($result['districts'] as $district) {
        $districtType = $district['name'];
        switch ($districtType) {
          case 'County':
            $field = $district['districtFieldValues'][0];
            $nl['county'] = $field['name'];
            break;
          case 'Precinct':
            $field = $district['districtFieldValues'][0];
            $nl['precinct'] = $field['name'];
            break;
          case 'State House':
            $field = $district['districtFieldValues'][0];
            $nl['hd'] = $field['name'];
            break;
        }
      }
    }
    return $nl;
    
  }

  /** @noinspection PhpUnused */
  public function getNlsPhones($committeeKey, $mcid): array
  {
    $nlpPhoneTypes = array_flip($this->phoneTypes);
    $expandOptions = '?$expand=phones';
    $result = $this->nlApiCall($committeeKey,$mcid,$expandOptions);
    if(empty($result)) {return [];}
    $phones = array();
    if(!empty($result['phones'])) {
      foreach ($result['phones'] as $phone) {
        if(!empty($nlpPhoneTypes[$phone['phoneType']])) {
          $phones[$phone['phoneId']] = $phone;
        }
      }
    }
    return $phones;
  }

  /** @noinspection PhpUnused */
  public function getNlsEmails($committeeKey, $mcid): array
  {
    $nlpEmailTypes = array_flip($this->emailTypes);
    $expandOptions = '?$expand=emails';
    $result = $this->nlApiCall($committeeKey,$mcid,$expandOptions);
    if(empty($result)) {return [];}
    $emails = array();
    if(!empty($result['emails'])) {
      //$index = 0;
      foreach ($result['emails'] as $email) {
        if(!empty($nlpEmailTypes[$email['type']])) {
          $emails[$email['email']] = $email;
        }
      }
    }
    return $emails;
  }

  /** @noinspection PhpUnused */
  public function updateNlsEmail($committeeKey, $emailObj, $peopleObj) {
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    
    $post_url = 'https://'.$user.':'.$apiKey.'|1@'.$apiURL.'/people/'.$peopleObj->mcid;
    $emailObj->type = $this->emailTypes[$emailObj->type];
    $peopleObj->vanId = $peopleObj->mcid;
    unset($peopleObj->mcid);
    $peopleObj->emails = array($emailObj);
    $dataJson = json_encode($peopleObj);
  
    $headers = array('Content-Type' => 'application/json');
    try {
      $this->client->post($post_url, array('headers' => $headers, 'body' => $dataJson));
    } catch (Exception $e) {
      nlp_debug_msg('Export jobs error.', $e->getMessage());
      return;
    }
  }

  /** @noinspection PhpUnused */
  public function updateNlsPhone($committeeKey, $phoneObj, $peopleObj) {
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    
    $post_url = 'https://'.$user.':'.$apiKey.'|1@'.$apiURL.'/people/'.$peopleObj->mcid;
    $phoneObj->phoneType =  $this->phoneTypesExt[$phoneObj->type];
    unset($phoneObj->type);
    $peopleObj->vanId = $peopleObj->mcid;
    unset($peopleObj->mcid);
    $peopleObj->phones = array($phoneObj);
    $dataJson = json_encode($peopleObj);
    $headers = array('Content-Type' => 'application/json');
    //nlp_debug_msg('$post_url',$post_url);
    //nlp_debug_msg('$dataJson',$dataJson);
    try {
      $this->client->post($post_url, array('headers' => $headers, 'body' => $dataJson));
    } catch (Exception $e) {
      nlp_debug_msg('Update NL phone error.', $e->getMessage());
      $snag = $e->getMessage();
      nlp_debug_msg('Update NL phone error text.', $snag);
      return;
    }
  }
  
  
}
