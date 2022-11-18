<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;
use Drupal\nlpservices\NlpReports;

/**
 * @noinspection PhpUnused
 */
class ExportVoterContactReportsForm extends FormBase
{

  const NR_NLS_REPORTS = 'voter_contact_reports';

  protected NlpReports $reports;

  public function __construct( $reports) {
    $this->reports = $reports;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ExportVoterContactReportsForm
  {
    return new static(
      $container->get('nlpservices.reports'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_export_reports_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if (!empty($form_state->get('reenter'))) {
      $args = $form_state->get('args');
      $form['count'] = [
        '#markup' => 'Count of voter contact reports in database: '.$args['rowCount'],
      ];
      $url = Drupal::service('file_url_generator')->generateAbsoluteString($args['uri']);
      $form['file'] = [
        '#markup' =>  '<p><a  href='.$url.'>Right-click here</a> to download the NLs reports. </p>',
      ];
    } else {
      $form['currentCycle'] = [
        '#type' => 'checkbox',
        '#title' => 'Export only the current election.',
      ];
      $form['upload_file'] = [
        '#type' => 'submit',
        '#id' => 'export-file',
        '#value' => 'Export the voter contact reports database',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->set('reenter', TRUE);
    $form_state->setRebuild();
    $currentCycleOnly = $form_state->getValue('currentCycle');
    //nlp_debug_msg('$currentCycleOnly',$currentCycleOnly);
    $args = $this->exportVoterContactReports($currentCycleOnly);
    $form_state->set('args', $args);
  }


  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * exportVoterContactReports
   *
   * Export the NL reports for a county and put them in a tab delimited file
   * suitable for download.  The file can be used for archive of an election
   * cycle or for import to the VoteBuilder.
   *
   * The fields in the nlpservices results table are selected and written to a file.
   * The VANID is moved to the first field in the file to make import to the
   * VoteBuilder easier.   The last field is called EOR and will contain the EOR
   * text to meet the VoteBuilder requirements that the last field always has
   * information. The nickname and last name of the NL is included to make the
   * export file a little easier to read.
   *
   * @param $currentCycleOnly
   * @return array
   */
  function exportVoterContactReports($currentCycleOnly): array
  {
    $messenger = Drupal::messenger();
    // Use the public folder for saving temp files.
    $tempDir = 'public://temp';
    // Use a date in the name to make the file unique., just in case two people
    // are doing an export at the same time.
    $createDate = date('Y-m-d-H-i-s',time());
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    $type = ($currentCycleOnly)?'cycle-'.$cycle:'all-cycles';
    // Open a temp file for receiving the records.
    $fileName = self::NR_NLS_REPORTS.'_'.$type.'_date'.'-'.$createDate.'.csv';
    $tempUri = $tempDir.'/'.$fileName;
    // Create a managed file for temporary use.  Drupal will delete after 6 hours.
    $file = Drupal::service('file.repository')->writeData('', $tempUri, FileSystemInterface::EXISTS_REPLACE);
    $file->setTemporary();
    try{
      $file->save();
    }
    catch (Exception $e) {
      nlp_debug_msg('error', $e->getMessage() );
      return [];
    }
    // Open the new temp file for writing by PHP functions.
    $fh = fopen($tempUri,"w");
    // Get the column names for the export and add the NL name to ease editing.
    $columnNames = $this->reports->getColumnNames();
    $columnNames[] = 'nickname';
    $columnNames[] = 'lastName';
    $columnNames[] = 'cd';
    $columnNames[] = 'EOR';
    // Write the header as the first record in this tab delimited file.
    $string = implode(",", $columnNames)."\n";
    fwrite($fh,$string);
    fclose($fh);
    
    $countCycle = ($currentCycleOnly)?$cycle:NULL;
    $rowCount = $this->reports->getReportCount($countCycle);
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    $args = [
      'uri' => $tempUri,
      'columnNames' => $columnNames,
      'rowCount' => $rowCount,
      'singleCycle' => $currentCycleOnly,
      'cycle' => $cycle,
    ];
    $batch = [
      'operations' => [
        ['exportVoterContactReportsBatch', array($args)]
      ],
      'file' => $modulePath.'/src/Form/ExportVoterContactReportsBatch.php',
      'finished' => 'exportVoterContactReportsBatchFinished',
      'title' => 'Processing export reports.',
      'init_message' => 'Reports export is starting.',
      'progress_message' => 'Processed @percentage % of reports database.',
      'error_message' => 'Export reports has encountered an error.',
    ];
    //nlp_debug_msg('$batch',$batch);
    batch_set($batch);
    //$messenger->addStatus('Batch started');
    return $args;
  }
}
