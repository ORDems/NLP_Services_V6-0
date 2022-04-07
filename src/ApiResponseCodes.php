<?php

namespace Drupal\nlpservices;

use Drupal;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class ApiResponseCodes
{
  private  array $expectedContactTypes = array(
    'Walk' => array(
      array('text'=>'Left Message/Lit','weight'=>1),
      array('text'=>'Not Home','weight'=>11),
      array('text'=>'Refused','weight'=>2),
      array('text'=>'Deceased','weight'=>7),
      array('text'=>'Hostile','weight'=>6),
      array('text'=>'Moved','weight'=>5),
    ),
    'Phone'=>array(
      array('text'=>'Left Message','weight'=>8),
      array('text'=>'Deceased','weight'=>7),
      array('text'=>'Hostile','weight'=>6),
      array('text'=>'Wrong Number','weight'=>9),
    ),
    'Postcard'=>array(
      array('text'=>'Mailed','weight'=>5),
      array('text'=>'Deceased','weight'=>7),
      array('text'=>'Hostile','weight'=>6),
    ),
    'Personal Email'=>array(
      array('text'=>'Sent Email','weight'=>5),
      array('text'=>'Deceased','weight'=>7),
      array('text'=>'Hostile','weight'=>6),
      array('text'=>'Do Not Email','weight'=>9),
    ),
    'SMS Text'=>array(
      array('text'=>'Texted','weight'=>5),
      array('text'=>'Deceased','weight'=>7),
      array('text'=>'Hostile','weight'=>6),
      array('text'=>'Do Not Text','weight'=>9),
    ),
    'Social Media'=>array(
      array('text'=>'Left Message','weight'=>5),
    ),
    'Relational Canvass' => array(
      array('text'=>'Left Message/Lit','weight'=>1),
      array('text'=>'Not Home','weight'=>11),
      array('text'=>'Refused','weight'=>2),
      array('text'=>'Deceased','weight'=>7),
      array('text'=>'Hostile','weight'=>6),
      array('text'=>'Moved','weight'=>5),
    ),
  );
  
  protected ClientInterface $client;
  
  public function __construct( $client) {
    $this->client = $client;
  }
  
  public static function create(ContainerInterface $container): ApiResponseCodes
  {
    return new static(
      $container->get('http_client'),
    );
  }

  public function getApiContactTypes($committeeKey, $database): array
  {
    $messenger = Drupal::messenger();
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    $post_url = 'https://'.$user.':'.$apiKey.'|'.$database.'@'.$apiURL.'/canvassResponses/contactTypes?inputTypeId=11';
    //nlp_debug_msg('$post_url',$post_url);
    try {
      $request = $this->client->get($post_url);
      $result = json_decode($request->getBody(), true);
      //nlp_debug_msg('$result',$result);
    } catch (Exception $e) {
      $messenger->addStatus( t('An error occurred. Please contact the Administrator.'));
      return [];
    }
    $contactTypes = array();
    foreach ($result as $contactType) {
      $contactTypes[$contactType['name']] = $contactType['contactTypeId'];
    }
    return $contactTypes;
  }

  public function getApiResultCodes($committeeKey, $database, $contactTypeId): array
  {
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    $url = 'https://'.$apiURL.'/canvassResponses/resultCodes?contactTypeId='.$contactTypeId;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, "Content-type: application/json");
    curl_setopt($ch, CURLOPT_USERPWD, $user.':'.$apiKey.'|'.$database);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if($result === FALSE) {
      nlp_debug_msg('curl error', curl_error($ch));
      return [];
    }
    curl_close($ch);
    $resultArray = json_decode($result);
    $resultCodes = array();
    foreach ($resultArray as $resultCodeObj) {
      $resultCodes[$resultCodeObj->name] = $resultCodeObj->resultCodeId;
    }
    return $resultCodes;
  }

  public function getApiKnownResultCodes($committeeKey,$database): array
  {
    $contactTypes = $this->getApiContactTypes($committeeKey,$database);
    $knownResultCodes = array();
    foreach ($contactTypes as $contactTypeName => $contactTypeId) {
      $knownResultCodes[$contactTypeName]['code'] = $contactTypeId;
      $resultCodes = $this->getApiResultCodes($committeeKey,$database,$contactTypeId);
      //nlp_debug_msg('$contactTypeId '.$contactTypeId.' $resultCodes',$resultCodes);
      foreach ($resultCodes as $resultName => $resultCodeId) {
        $knownResultCodes[$contactTypeName]['responses'][$resultName] = $resultCodeId;
      }
    }
    return $knownResultCodes;
  }

  /** @noinspection PhpUnused */
  /*
  public function getResultDisplayList(): array
  {
    $resultList = array();
    foreach ($this->expectedContactTypes as $eResultArray) {
      foreach ($eResultArray as $expectedResultArray) {
        $resultList[$expectedResultArray['weight']] = $expectedResultArray['text'];
      }
    }
    ksort($resultList);
    return $resultList;
  }
*/
  public function getApiExpectedResultCodes(): array
  {
    $expectedResultCodes = array();
    foreach ($this->expectedContactTypes as $contactType=>$eResultArray) {
      foreach ($eResultArray as $expectedResultArray) {
        $text = $expectedResultArray['text'];
        $expectedResultCodes[$contactType]['responses'][$text] = $text;
      }
    }
    return $expectedResultCodes;
  }
}