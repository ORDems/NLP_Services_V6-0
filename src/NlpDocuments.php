<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Database\Connection;
use Drupal\nlpservices\NlpPaths;
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
    'nlList' => array('description' =>'Managing the list of Active NLs','weight'=>30,
      'defaultFilename' => 'ActiveNLList'),
    'cutTurf' => array('description' =>'Cutting turf','weight'=>40,
      'defaultFilename' => 'CuttingTurf'),
    'deliverTurf' => array('description' =>'Sending a turf to an NL','weight'=>50,
      'defaultFilename' => 'TurfDelivery'),
    'countyCoordinator' => array('description' =>'Defining the county coordinators','weight'=>60,
      'defaultFilename' => 'CountyCoordinator'),
    'editEmail' => array('description' =>'Edit turf delivery email','weight'=>70,
      'defaultFilename' => 'EditTurfDeliveryEmail'),
  );
  
    protected Connection $connection;
    protected NlpPaths $paths;

    public function __construct( $connection, $paths) {
      $this->connection = $connection;
      $this->paths = $paths;
    }
  
  public static function create(ContainerInterface $container): NlpDocuments
  {
    return new static(
      $container->get('database'),
      $container->get('nlpservices.paths'),
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


    function buildDocumentDisplay(): array
    {
        $documents = $this->getDocuments();
        if(empty($documents)) {
            $page['documents'] = ['Error.'];
        }
        
        $docPath = $this->paths->getPath('DOCS',NULL);
        $page['document_form'] = array(
            '#title' => 'Available NLP Documents.  (Right click the link to download the document.)',
            '#type' => 'fieldset',
            '#prefix' => '<div>',
            '#suffix' => '</div>',
        );

        $header = [
            'name' => t('Name'),
            'description' => t('Description'),
            'doc' => t('Docx'),
            'pdf' => t('PDF')
        ];

        $rows = [];
        foreach ($documents as $document) {

            if(!empty($document['docFileName'])) {
                $docxUri = $docPath . $document['docFileName'];
                //$docUrl = file_create_url($docxUri);
                $docUrl = Drupal::service('file_url_generator')->generateAbsoluteString($docxUri);
                $doc = '(<a href="'.$docUrl.'">'.$document['docFileName'].'</a>) ';
            } else {
                $doc = '-';
            }

            if(!empty($document['pdfFileName'])) {
                $pdfUri = $docPath . $document['pdfFileName'];
                //$pdfUrl = file_create_url($pdfUri);
                $pdfUrl = Drupal::service('file_url_generator')->generateAbsoluteString($pdfUri);
                $pdf = '(<a href="'.$pdfUrl.'">'.$document['pdfFileName'].'</a>) ';
            } else {
                $pdf = '-';
            }

            $row = [
                'name' => t($document['name']),
                'description' => t($document['description']),
                'doc' => t($doc),
                'pdf' => t($pdf),
            ];
            $rows[$document['name']] = $row;
        }
        //nlp_debug_msg('$rows',$rows);

        $page['document_form']['table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => t('No documents found.'),
        );
        return $page;
    }


}
