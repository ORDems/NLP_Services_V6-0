<?php

namespace Drupal\nlpservices\Config;

use Drupal;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;
//use Drupal\nlpservices\Form\ActiveNlsDisplayForm;
use Drupal\nlpservices\NlpNls;
//use Drupal\nlpservices\NlpReports;
use Drupal\nlpservices\NlpTurfs;
use Drupal\nlpservices\NlpVoters;
use Drupal\nlpservices\NlpMatchbacks;
use Drupal\nlpservices\ApiExportJobs;
use Drupal\nlpservices\NlpCrosstabCounts;
use Drupal\nlpservices\NlpInstructions;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @noinspection PhpUnused
 */
class NlpResetForm extends FormBase {
  
  protected NlpVoters $votersObj;
  protected NlpNls $nlsObj;
  protected NlpTurfs $turfsObj;
  protected NlpMatchbacks $matchbackObj;
  protected ApiExportJobs $exportJobsObj;
  protected NlpCrosstabCounts $crosstabCountsObj;
  protected NlpInstructions $instructionsObj;
  protected Connection $connection;
  
  
  public function __construct( $votersObj, $nlsObj, $turfsObj,$matchbackObj ,$exportJobsObj ,$crosstabCountsObj,
                               $instructionsObj, $connection ) {
    $this->votersObj = $votersObj;
    $this->nlsObj = $nlsObj;
    $this->turfsObj = $turfsObj;
    $this->matchbackObj = $matchbackObj;
    $this->exportJobsObj = $exportJobsObj;
    $this->crosstabCountsObj = $crosstabCountsObj;
    $this->instructionsObj = $instructionsObj;
    $this->connection = $connection;
  
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): NlpResetForm
  {
    return new static(
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.turfs'),
      $container->get('nlpservices.matchbacks'),
      $container->get('nlpservices.export_jobs'),
      $container->get('nlpservices.crosstab_counts'),
      $container->get('nlpservices.instructions'),
      $container->get('database'),

    );
  }
  
 
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlp_reset_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['submit-co'] = array(
      '#type' => 'submit',
      '#value' => t('Okay to reset NLP Services.'),
      '#name' => 'add_coordinator',
    );
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();
  
    //$values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    
    $tablesToTruncate = [
      'voters' => $this->votersObj::VOTER_TBL,
      'voterStatus' => $this->votersObj::VOTER_STATUS_TBL,
      'voterAddress' => $this->votersObj::VOTER_ADDRESS_TBL,
      'voterTurf' => $this->votersObj::VOTER_TURF_TBL,
  
      'nls' => $this->nlsObj::NLS_TBL,
      'nlsGroup' => $this->nlsObj::NLS_GRP_TBL,
      'nlsStatus' => $this->nlsObj::NLS_STATUS_TBL,
  
      'turf' => $this->turfsObj::TURF_TBL,
  
      'ballotCount' => $this->crosstabCountsObj::CROSSTAB_COUNTS_TBL,
      'matchback' => $this->matchbackObj::MATCHBACK_TBL,
      'exportJobs' => $this->exportJobsObj::EXPORT_JOBS_TBL,
      'instructions' => $this->instructionsObj::INSTRUCTIONS_TBL,

    ];
    
    foreach ($tablesToTruncate as $table) {
      try {
        $this->connection->truncate($table)->execute();
        $messenger->addStatus('The '.$table.' database was emptied.');
      }
      catch (Exception $e) {
        nlp_debug_msg('e', $e->getMessage() );
      }
    }
  
    $nlpFilesDir = 'public://nlp_files';
  
    $counties = $this->getDirContent($nlpFilesDir);
    //nlp_debug_msg('$counties',$counties);
  
    foreach ($counties['directories'] as $countyDir) {
      $countyDir = $nlpFilesDir.'/'.$countyDir;
      //nlp_debug_msg('$countyDir',$countyDir);
      $countyDirContent = $this->getDirContent($countyDir);
      //nlp_debug_msg('$countyDirContent',$countyDirContent);
    
      foreach ($countyDirContent['files'] as $countyFile) {
        $fileName = $countyDir.'/'.$countyFile;
        //nlp_debug_msg('$fileName',$fileName);
        /*
        if(file_exists($fileName)) {
          nlp_debug_msg('file exists',$fileName);
        }
        */
        unlink($fileName);
      }
    
      foreach ($countyDirContent['directories'] as $countyContentDir) {
        $countyContentDir = $countyDir.'/'.$countyContentDir;
        //nlp_debug_msg('$countyContentDir',$countyContentDir);
        $countyContentDirContent = $this->getDirContent($countyContentDir);
        //nlp_debug_msg('$countyContentDirContent',$countyContentDirContent);
      
        foreach ($countyContentDirContent['files'] as $countyFile) {
          $fileName = $countyContentDir.'/'.$countyFile;
          //nlp_debug_msg('$fileName',$fileName);
        /*
          if(file_exists($fileName)) {
            nlp_debug_msg('file exists',$fileName);
          }
          */
          unlink($fileName);
          
        }
      }
    
    }
    
  
  }
  
  function getDirContent($dir): array
  {
    $results = ['files'=>[],'directories'=>[]];
    if (!is_dir($dir)){
      return $results;
    }
    if ($dh = opendir($dir)) {
      while (($file = readdir($dh)) !== false) {
        if ($file != '.' and $file != '..') {
          if (is_dir($dir.'/'.$file)) {
            $results['directories'][] = $file;
          } else {
            $results['files'][] = $file;
          }
        }
      }
      closedir($dh);
    }
    return $results;
  }
  
}
