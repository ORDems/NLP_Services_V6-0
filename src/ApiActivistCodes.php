<?php

namespace Drupal\nlpservices;

use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApiActivistCodes
{
  
  protected ClientInterface $client;
  
  public function __construct( $client) {
    $this->client = $client;
  }
  
  public static function create(ContainerInterface $container): ApiActivistCodes
  {
    return new static(
      $container->get('http_client'),
    );
  }
  
  public function getApiActivistCodes($committeeKey, $database): array
  {
    //$messenger = \Drupal::messenger();
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    $post_url = 'https://'.$user.':'.$apiKey.'|'.$database.'@'.$apiURL.'/activistCodes?$top=200';
    //try {
      $request = $this->client->get($post_url);
      $results = json_decode($request->getBody(), true);
      //nlp_debug_msg('$results',$results);
    /*
    } catch (RequestException $e) {
      $messenger->addStatus( $this->t('An error occurred. Please contact the Administrator.'));
      return [];
    }
    */
    return $results['items'];
  }
  /*
  public function getActivistCodeList($activistCodes): array
  {
    $activistCodeList[1] = 'Select an Activist Code';
    foreach ($activistCodes as $activistCodeId => $activistCode) {
      $activistCodeList[$activistCodeId] = 'name:"'.$activistCode['name'].
        '", type="'.$activistCode['type'].'"';
    }
    return $activistCodeList;
  }
  */
}