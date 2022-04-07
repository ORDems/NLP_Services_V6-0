<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpCrosstabCounts;

/**
 * @noinspection PhpUnused
 */
class ImportCrosstabsForm extends FormBase
{
  protected NlpCrosstabCounts $crosstabsObj;

  public function __construct( $crosstabsObj) {
      $this->crosstabsObj = $crosstabsObj;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ImportCrosstabsForm
  {
      return new static(
          $container->get('nlpservices.crosstab_counts'),
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
      return 'nlpservices_import_crosstabs_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['hint'] = array(
      '#type' => 'markup',
      '#markup' => '
The Demographics file is a cross tab report that counts ballots received for
each party by county. The list of voters used to create the cross tab report
should be of just active registered voters and the cross tab file is for the
entire state.',
    );
    // Name of the crosstabs file to upload
    $form['crosstabs_file'] = array(
      '#type' => 'file',
      '#title' => t('Demographics file name'),
    );

    $form['upload_file'] = array(
      '#type' => 'submit',
      '#id' => 'upload-file',
      '#value' => t('Import the Demographics file'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();

    // Verify we have a good VAN export file with the needed fields
    $tempName = $_FILES['files']['tmp_name']['crosstabs_file'];

    $fh = fopen($tempName, "r");
    if ($fh == FALSE) {
      $form_state->setErrorByName('crosstabs_file', 'Failed to open count export file.');
      return;
    }
    $header1 = $this->getHeaderRecord($fh);
    //nlp_debug_msg('$header1',$header1);
    if (empty($header1)) {
      $form_state->setErrorByName('crosstabs_file', 'Failed to read VAN count export header.');
      return;
    }
    $header2 = $this->getHeaderRecord($fh);
    //nlp_debug_msg('$header2',$header2);
    if (empty($header2)) {
      $messenger->addError('Failed to read VAN count export header.');
      $form_state->setErrorByName('crosstabs_file', 'Failed to read VAN count export header.');
      return;
    }
    $pos = $this->crosstabsObj->decodeCrosstabsHeader($header1,$header2);
    $fieldPos = $pos['pos'];
    if (!empty($pos['err'])) {
      foreach($pos['err'] as $err) {
        $messenger->addError($err);
      }
      $form_state->setErrorByName('crosstabs_file', 'Fix the problem before resubmit.');
      return;
    }
    $form_state->set('crosstabs_name', $tempName);
    $form_state->set('field_pos', $fieldPos);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();

    $crosstabsName = $form_state->get('crosstabs_name');
    $fieldPos = $form_state->get('field_pos');
    $fh = fopen($crosstabsName, "r");
    if (empty($fh)) {
      $messenger->addError('Failed to open crosstab file.');
      return;
    }
    // Discard the header records.  There are two for the counts export.
    fgetcsv($fh);
    fgetcsv($fh);

    $counties = [];
    do {
      $rawCount = fgetcsv($fh);
      if (!$rawCount) {break;} //We've processed the last count.
      // Parse the count record into the various fields.
      // Get the county name, party, and counts.
      $countsInfo = array();
      foreach ($rawCount as $countField) {
        $countsInfo[] = trim(strip_tags(htmlentities(stripslashes($countField),ENT_QUOTES)));
      }
      $rawCounty = $countsInfo[$fieldPos['county']];
      $county = ($rawCounty == "Hood River")? "Hood_River": $rawCounty;
      if ($county != 'Total People') {
        $party = $countsInfo[$fieldPos['party']];

        $ballotsReceived = str_replace(',', '', $countsInfo[$fieldPos['balRet']]);
        $registeredVoters = str_replace(',', '', $countsInfo[$fieldPos['total']]);
        // Record the numbers for a party.
        $counties[$county][$party]['br'] = $ballotsReceived;
        $counties[$county][$party]['reg'] = $registeredVoters;
        // Sum the party numbers for the county.
        if (!isset($counties[$county]['ALL'])) {
          $counties[$county]['ALL']['br'] =
          $counties[$county]['ALL']['reg'] = 0;
        }
        $counties[$county]['ALL']['br'] += $ballotsReceived;
        $counties[$county]['ALL']['reg'] += $registeredVoters;
      }
    } while (TRUE);
    // = new NlpCrosstabCounts();
    $counts = [];
    foreach ($counties as $county => $countyCounts) {
      foreach ($countyCounts as $party => $partyCounts) {
        $counts = array(
          'county' => $county,
          'party' => $party,
          'regVoters' => $partyCounts['reg'],
          'regVoted' =>  $partyCounts['br'],
        );
        $this->crosstabsObj->updateCrosstabCounts($counts);
      }
    }
    if (empty($counts)) {return;}
    $messenger->addStatus('The ballot count file has been successfully uploaded.');
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * getHeaderRecord
   *
   * Get a header record from the file.
   *
   * @param $fh
   * @return array|false
   */
  function getHeaderRecord($fh) {
    $rawHeader = fgetcsv($fh);
    if (!$rawHeader) {
      return FALSE;
    }
    $headerColumns = [];
    foreach ($rawHeader as $hdrColumn) {
      $headerColumns[] = trim(strip_tags(htmlentities(stripslashes($hdrColumn),ENT_QUOTES)));
    }
    return $headerColumns;
  }

}