<?php

namespace Drupal\nlpservices\Config;

//use Drupal;
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
  protected NlpCrosstabCounts $ballotCountObj;
  protected NlpInstructions $instructionsObj;
  protected Connection $connection;
  
  
  public function __construct( $votersObj, $nlsObj, $turfsObj,$matchbackObj ,$exportJobsObj ,$ballotCountObj,
                               $instructionsObj, $connection ) {
    $this->votersObj = $votersObj;
    $this->nlsObj = $nlsObj;
    $this->turfsObj = $turfsObj;
    $this->matchbackObj = $matchbackObj;
    $this->exportJobsObj = $exportJobsObj;
    $this->ballotCountObj = $ballotCountObj;
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
      $container->get('nlpservices.ballot_count'),
      $container->get('nlpservices.instructions'),
      $container->get('nlpservices.database'),

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
    $values = $form_state->getValues();
    nlp_debug_msg('$values',$values);
    
    $tablesToTruncate = [
      'voters' => $this->votersObj::VOTER_TBL,
      'voterStatus' => $this->votersObj::VOTER_STATUS_TBL,
      'voterAddress' => $this->votersObj::VOTER_ADDRESS_TBL,
      'voterTurf' => $this->votersObj::VOTER_TURF_TBL,
  
      'nls' => $this->nlsObj::NLS_TBL,
      'nlsGroup' => $this->nlsObj::NLS_GRP_TBL,
      'nlsStatus' => $this->nlsObj::NLS_STATUS_TBL,
  
      'turf' => $this->turfsObj::TURF_TBL,
  
      'ballotCount' => $this->ballotCountObj::CROSSTAB_COUNTS_TBL,
      'matchback' => $this->matchbackObj::MATCHBACK_TBL,
      'exportJobs' => $this->exportJobsObj::EXPORT_JOBS_TBL,
      'instructions' => $this->instructionsObj::INSTRUCTIONS_TBL,

    ];
    
    foreach ($tablesToTruncate as $table) {
      try {
        $this->connection->truncate($table)->execute();
      }
      catch (Exception $e) {
        nlp_debug_msg('e', $e->getMessage() );
      }
    }
    
    
  }
  
}
