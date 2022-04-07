<?php

namespace Drupal\nlpservices;

use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApiFolders {
  
  protected ClientInterface $client;
  
  public function __construct($client) {
    $this->client = $client;
  }
  
  public static function create(ContainerInterface $container): ApiFolders
  {
    return new static(
      $container->get('http_client'),
    );
  }
  
  public function getApiFolders($committeeKey,$database,$folderId): array
  {
    //$messenger = \Drupal::messenger();
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    
    $foldersURL = 'https://'.$user.':'.$apiKey.'|'.$database.'@'.$apiURL.'/folders';
    if(!empty($folderId)) {
      $foldersURL .= '/'.$folderId;
    }
   
    //nlp_debug_msg('$foldersURL',$foldersURL);
    //try {
      $request = $this->client->get($foldersURL);
      $results = json_decode($request->getBody(), true);
      //nlp_debug_msg('$results',$results);
    /*
    } catch (Exception $e) {
      $messenger->addStatus( $this->t('An error occurred. Please contact the Administrator.'));
      return [];
    }
    */
    $folderArray = array();
    if(empty($folderId)) {
      if(empty($results['items'])) {
        return [];
      }
      foreach ($results['items'] as $folderInfo) {
        $folderArray[$folderInfo['folderId']] = $folderInfo['name'];
      }
    } else {
      $folderArray[$results['folderId']] = $results['name'];
    }
    return $folderArray;
  }
  
}
