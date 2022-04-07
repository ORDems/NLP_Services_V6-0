<?php

namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class NlpDocuments
{
  const DOCUMENTS_TBL = "nlp_documents";
  
  private array $documentsList = array(
    'name' => 'name',
    'weight' => 'weight',
    'docFileName' => 'docFileName',
    'pdfFileName' => 'pdfFileName',
    'description' => 'description',
  );
  
  public array $nameList = array(
    'nlInstruct' => array('description' =>'Neighborhood Leader Instructions - canvass','weight'=>10,
      'defaultFilename' => 'InstructionsCanvass'),
    'nlPostcard' => array('description' =>'Neighborhood Leader Instructions - postcard','weight'=>20,
      'defaultFilename' => 'InstructionsPostcard'),
    'nlList' => array('description' =>'Managing the list of Potential NLs','weight'=>30,
      'defaultFilename' => 'ProspectiveNLList'),
    'cutTurf' => array('description' =>'Cutting turf','weight'=>40,
      'defaultFilename' => 'CuttingTurf'),
    'deliverTurf' => array('description' =>'Sending a turf to an NL','weight'=>50,
      'defaultFilename' => 'TurfDelivery'),
  );
  
  protected Connection $connection;
  
  public function __construct( $connection) {
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): NlpDocuments
  {
    return new static(
      $container->get('database'),
    );
  }

  
  public function createDocument($req) {
    try {
      $this->connection->merge(self::DOCUMENTS_TBL)
        ->keys(array(
          'name' => $req['name'],))
        ->fields(array(
          'weight' => $req['weight'],
          'docFileName' => $req['docFileName'],
          'pdfFileName' => $req['pdfFileName'],
          'description' => $req['description']))
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e',$e->getMessage());
      return;
    }
  }
  
  public function getDocuments(): array
  {
    $dbList = array_flip($this->documentsList);
  
    $query = $this->connection->select(self::DOCUMENTS_TBL, 'd')
      ->fields('d');
    $result = $query->execute();
    
    $docs =  array();
    do {
      $record = $result->fetchAssoc();
      //nlp_debug_msg('$record',$record);
      if(empty($record)) {break;}
      $doc = array();
      foreach ($record as $dbKey => $value) {
        $doc[$dbList[$dbKey]] = $value;
      }
      $docs[$doc['name']] = $doc;
    } while (TRUE);
    if(empty($docs)) {return $docs;}
    $name  = array_column($docs, 'name');
    $weight = array_column($docs, 'weight');
    array_multisort($weight, SORT_ASC, $name, SORT_ASC, $docs);
    return $docs;
  }
  
  public function  displayList(): array
  {
    $displayList = array();
    foreach ($this->nameList as $documentId => $documentInfo) {
      $displayList[$documentId] = $documentInfo['description'];
    }
    return $displayList;
  }
  
}
