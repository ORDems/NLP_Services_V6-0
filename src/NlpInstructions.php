<?php

namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class NlpInstructions {
  
  const INSTRUCTIONS_TBL = "nlp_instructions";
 
  private array $instructionsList = ['county','type','fileName','originalFileName','title','blurb'];
  
  protected Connection $connection;
  
  public function __construct( $connection) {
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): NlpInstructions
  {
    return new static(
      $container->get('database'),
    );
  }
  
  public function createInstructions($req) {
    $originalFileName = (!empty($req['originalFileName']))?$req['originalFileName']:NULL;
    try {
      $this->connection->merge(self::INSTRUCTIONS_TBL)
        ->keys(array(
          'county' => $req['county'],
          'type' => $req['type'],))
        ->fields(array(
          'fileName' => $req['fileName'],
          'originalFileName' => $originalFileName,
          'title' => $req['title'],
          'blurb' => $req['blurb'],))
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return;
    }
  }
  
  public function getInstructions($county): array
  {
    $rec = [];
    foreach ($this->instructionsList as $key) {
      $rec[$key] = '';
    }
    $instructs = array(
      'canvass'=>$rec,
      'postcard'=>$rec,
    );
    $query = $this->connection->select(self::INSTRUCTIONS_TBL, 'i');
    $query->fields('i');
    $query->condition('county',$county);
    $result = $query->execute();
    do {
      $instruct = $result->fetchAssoc();
      //nlp_debug_msg('$instruct',$instruct);
      if(empty($instruct)) {break;}
      $instructs[$instruct['type']] = $instruct;
    } while (TRUE);
    //nlp_debug_msg('$instructs',$instructs);
    return $instructs;
  }
  
}
