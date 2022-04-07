<?php

namespace Drupal\nlpservices;

//use Drupal;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use Cassandra\Exception;

class ApiSavedLists {
  
  protected ClientInterface $client;
  
  public function __construct($client) {
    $this->client = $client;
  }
  
  public static function create(ContainerInterface $container): ApiSavedLists
  {
    return new static(
      $container->get('http_client'),
    );
  }
  
  public function getSavedLists($committeeKey,$database, $folderId): array
  {
    //$messenger = Drupal::messenger();
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    $listsURL = 'https://'.$user.':'.$apiKey.'|'.$database.
      '@'.$apiURL.'/savedLists?folderId='.$folderId;
    $count = 0;
    $totalList = [];
    do {
      $url = $listsURL;
      if(!empty($count)) {
        $url .= '&$skip='.$count;
      }
      //try {
        $request = $this->client->get($url);
        $results = json_decode($request->getBody(), true);
        //nlp_debug_msg('$results',$results);
      //} catch (Exception $e) {
      //  $messenger->addStatus( t('An error occurred. Please contact the Administrator.'));
      //  return [];
      //}
      $fragmentCount = count($results['items']);
      $count += $fragmentCount;
      $totalCount = $results['count'];
      foreach ($results['items'] as $item) {
        $totalList[$item['savedListId']] = $item;
      }
      if($count >= $totalCount) {break;}
  
    } while (TRUE);
    //nlp_debug_msg('$listsURL',$listsURL);
    
    return $totalList;
  }
  
}
