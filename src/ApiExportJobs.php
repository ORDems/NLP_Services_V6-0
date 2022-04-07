<?php
namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
use Exception;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApiExportJobs {
  
  const EXPORT_JOBS_TBL = 'nlp_export_jobs';

  protected ClientInterface $client;
  protected Connection $connection;
  
  public function __construct($client,  $connection) {
    $this->client = $client;
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): ApiExportJobs
  {
    return new static(
      $container->get('http_client'),
      $container->get('database'),
    );
  }
  public function getExportJobs($committeeKey,$jobObj,$listId,$county,$database): ?array
  {
    global $base_url;
    $req['county'] = $county;
    $req['listId'] = $listId;
    $req['startTime'] = time();
    $req['endTime'] = 0;
    $eventId = $this->insertExportJob($req);
  
    $apiKey = $committeeKey['API Key'];
    $apiURL = $committeeKey['Url'];
    $user = $committeeKey['App Name'];
    
    $post_url = 'https://'.$user.':'.$apiKey.'|'.$database.'@'.$apiURL.'/exportJobs';
    //nlp_debug_msg('post_url', $post_url);
    $jobObj->savedListId = $listId;
    $jobObj->type = 4;
    $jobObj->webhookUrl = $base_url."/nlp-webhook-callback?county=".$county."&listId=".$listId.'&eventId='.$eventId;
    $dataJson = json_encode($jobObj);
  
    $headers = array('Content-Type' => 'application/json');
    try {
      $response = $this->client->post($post_url, array('headers' => $headers, 'body' => $dataJson));
    } catch (Exception $e) {
      nlp_debug_msg('Export jobs error.', $e->getMessage());
      return [];
    }
    //nlp_debug_msg('$response',$response);
    $body = (string) $response->getBody();
    $dataObj = json_decode($body);
    //nlp_debug_msg('$dataObj',$dataObj);
    $exportJob['savedListId'] = $dataObj->savedListId;
    $exportJob['downloadUrl'] = $dataObj->downloadUrl;
    $exportJob['eventId'] = $eventId;
    return $exportJob;
  }
  
  public function insertExportJob($req) {
    try {
      $eventId = $this->connection->insert(self::EXPORT_JOBS_TBL)
        ->fields($req)
        ->execute();
    } catch (Exception $e) {
      return NULL;
    }
    return $eventId;
  }

  /** @noinspection PhpUnused */
  public function endExportJob($eventId): bool
  {
    $fields = array('endTime' => time());
    try {
      $this->connection->merge(self::EXPORT_JOBS_TBL)
        ->keys(array('eventId' => $eventId))
        ->fields($fields)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }
  
  public function getExportJobStatus($eventId): int
  {
    try {
      $query = $this->connection->select(self::EXPORT_JOBS_TBL, 'n');
      $query->condition('eventId',$eventId);
      $query->fields('n');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    $exportJob = $result->fetchAssoc();
    //nlp_debug_msg('$exportJob',$exportJob);
    if (empty($exportJob)) {return 0;}
    $elapsedTime = 0;
    if(!empty($exportJob['endTime'])) {
      $elapsedTime = $exportJob['endTime']-$exportJob['startTime'];
    }
    return $elapsedTime;
  }
  
}
