<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpVoters;
use Drupal\nlpservices\NlpReports;
use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\NlpTurfs;

/**
 * @noinspection PhpUnused
 */
class ActiveNlsDisplayForm extends FormBase
{

  const NLS_ALL_MAX = 500;
  
  protected NlpVoters $votersObj;
  protected NlpReports $reportsObj;
  protected NlpNls $nlsObj;
  protected NlpTurfs $turfsObj;
  
  
  public function __construct( $votersObj, $reportsObj, $nlsObj, $turfsObj) {
    $this->votersObj = $votersObj;
    $this->reportsObj = $reportsObj;
    $this->nlsObj = $nlsObj;
    $this->turfsObj = $turfsObj;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ActiveNlsDisplayForm
  {
    return new static(
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.reports'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.turfs'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_sync_active_nls_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if(empty($form_state->get('page'))) {
      //nlp_debug_msg('empty page');
      $form_state->set('page','data_entry');
      $sortable = [
        'hd'=>'HD','precinct'=>'Pct','lastName'=>'LastName','mcid'=>'MCID',
        'email'=>'Email','phone'=>'Phone',
        ];
      $form_state->set('sortable',$sortable);
  
      $factory = Drupal::service('tempstore.private');
      $store = $factory->get('nlpservices.session_data');
      $county = $store->get('County');
      $form_state->set('county',$county);
      //nlp_debug_msg('$county',$county);
  
      $nlpConfig = $this->config('nlpservices.configuration');
      $apiKeys = $nlpConfig->get('nlpservices-api-keys');
      $committeeKey = $apiKeys[$county];
      $form_state->set('committeeKey',$committeeKey);
  
      $electionDates = $nlpConfig->get('nlpservices-election-configuration');
      $cycle = $electionDates['nlp_election_cycle'];
      $form_state->set('cycle',$cycle);
  
      $countyNames = $nlpConfig->get('nlpservices-county-names');
      $form_state->set('state',$countyNames['State']);
  
      $hdOptions = $this->nlsObj->getHdList($county);
      //nlp_debug_msg('$hdOptions',$hdOptions);
      $form_state->set('hd-options',$hdOptions);
      $hd = reset($hdOptions);
      //nlp_debug_msg('$hd',$hd);
      $form_state->set('hd',$hd);
    }
  
    $county = $form_state->get('county');
    $page = $form_state->get('page');
    $cycle = $form_state->get('cycle');
    //nlp_debug_msg('$page', $page);
    $form = [];
    switch ($page) {
      case 'data_entry':
        
        // Select the HD to display.
        $hd = $form_state->get('hd');
        //nlp_debug_msg('$hd',$hd);
        $hdOptions = $form_state->get('hd-options');
        $sortable = $form_state->get('sortable');
  
        $form['county-name'] = [
          '#markup' => "<h1>".$county." County</h1>",
        ];
  
        // Add the line for selecting an HD and CSV download.
        $form['options_display'] = $this->nlp_options_display($hdOptions,$hd,$sortable);

        // Fetch the list of NL names and contact information.
        if(empty($form_state->get('nl-records'))) {
          $nlRecords = $this->nlsObj->getNls($county,$hd);
          //nlp_debug_msg('$nlRecords',$nlRecords);
          $nlKeys = array_keys($nlRecords);
          //nlp_debug_msg('$nlKeys',$nlKeys);
          foreach ($nlKeys as $mcid) {
            $nlRecords[$mcid]['progress']  = $this->nlp_get_progress($mcid,$cycle);
          }
          $form_state->set('nl-records', $nlRecords);
        } else {
          $nlRecords = $form_state->get('nl-records');
        }
        //nlp_debug_msg('$nlRecords',$nlRecords);
        $lists['askList'] = $this->nlsObj->askList;
        $lists['contactList'] = $this->nlsObj->contactList;
        $form_state->set('optionLists', $lists);
        
        // Build the table of NLs names and status.
        $form['nls_table'] = $this->nlp_build_nls_table($nlRecords,$lists);
        
        break;
   
    }
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    
    $county = $form_state->get('county');
    $optionsLists = $form_state->get('optionLists');
    
    $triggering_element = $form_state->getTriggeringElement();
    //nlp_debug_msg('$triggering_element',$triggering_element);
    $element_clicked = $triggering_element['#name'];
    //nlp_debug_msg('$element_clicked',$element_clicked);
    switch ($element_clicked) {
      case 'hd-submit':
        if($form_state->get('hd-select') == 0 AND $form_state->get('nlsCount') > self::NLS_ALL_MAX) {
          //nlp_set_msg('There are too many NLs to display them all.','error');
          $messenger->addError(t('There are too many NLs to display them all.'));
          $form_state->set('hd-select',1);
          $form_state->set('page','data_entry');
        } else {
            $form_state->set('hd',$form_state->getValue('hd-select'));
        }
        return;
        
      case 'sort-submit':
        return;
      
      default:
        // The triggering element names have the form type-mcid.
        $id_array = explode('-', $element_clicked);
        //nlp_debug_msg('$id_array',$id_array);
        if(empty($id_array[1])) {return;}
        $mcid = $id_array[1];  // MCID of affected NL.
        $status = $this->nlsObj->getNlsStatus($mcid,$county);
        $value = $triggering_element['#value'];
        // Process the checkbox, select or text box for this NL.
        switch ($id_array[0]) {
          case 'TD':  // Turf Delivered.
            // If the turf delivered status is set, update the date the turf was delivered.
            if($value) {
              $this->turfsObj->setAllTurfsDelivered($mcid,$county);
            }
            $cell_display = ($value)?'Y':'';
            $status['turfDelivered'] = $cell_display;
            $this->nlsObj->setNlsStatus($status);
            break;
          case 'TC':  // Turf cut.
            $cell_display = ($value)?'Y':'';
            $status['turfCut'] = $cell_display;
            $this->nlsObj->setNlsStatus($status);
            break;
          case 'TB':  // Notes (text box).
            $trunc = substr($value,0,$this->nlsObj::NOTES_MAX);
            $safe = htmlentities($trunc, ENT_QUOTES);
            $status['notes'] = $safe;
            //nlp_debug_msg('$status',$status);
            $this->nlsObj->setNlsStatus($status);
            break;
          case 'CO':  // Contact type (select); canvass, post card, phone.
            $contactList = $optionsLists['contactList'];
            $status['contact'] = $contactList[$value];
            //nlp_debug_msg('$status',$status);
            $this->nlsObj->setNlsStatus($status);
            break;
          case 'AS':  // Ask type (select); Default(NULL), Asked, Yes, No, Quit.
            $askList = $optionsLists['askList'];
            $status['asked'] = $askList[$value];
            //nlp_debug_msg('$status',$status);
            $this->nlsObj->setNlsStatus($status);
            break;
        }
        break;
    }
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->setRebuild();
    //$values = $form_state->getValues();
    //nlp_debug_msg('submit',$values);
    $triggering_element = $form_state->getTriggeringElement();
    //nlp_debug_msg('$triggering_element',$triggering_element);
    $element_clicked = $triggering_element['#name'];
    //nlp_debug_msg('$element_clicked',$element_clicked);
    switch ($element_clicked) {
      
      case 'hd-submit':
        // The user changed the HD to display.
        $form_state->set('hd',$form_state->getValue('hd-select'));
        //nlp_debug_msg('hd-select',$form_state->getValue('hd-select'));
        $form_state->set('nl-records',NULL);
        break;
      
      case 'sort-submit':
        $columnKey = $form_state->getValue('sort-select');
        //nlp_debug_msg('$columnKey',$columnKey);
        $nlRecords = $form_state->get('nl-records');
        $sorted_data = $this->nlp_sort_nls($nlRecords,$columnKey);
        //nlp_debug_msg('$sorted_data', $sorted_data);
        $form_state->set('nl-records',$sorted_data);
        break;
    
    }
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_get_progress
   *
   * Create an associate array with the progress of the NL to make contact with
   * the voters on the turf, the progress on making face-to-face contact and
   * a judgment that they are done.
   *
   * @param $mcid
   * @param $cycle
   * @return array
   */
  function nlp_get_progress($mcid,$cycle): array
  {
    $progress['attempts'] = $progress['contacts'] = $progress['voterCount'] = 0;
    // Get the voters assigned to this NL for all the turfs.
    $vanids= $this->votersObj->fetchVanIdsByNl($mcid);

    //nlp_debug_msg('$vanids',$vanids);
    if(empty($vanids)) {return $progress;}
    $voterCount = count($vanids);
    if ($voterCount == 0) {return $progress;}
    // Now get all the reports from this NL for this cycle.
    $reports = $this->reportsObj->getNlReportsForVoters($vanids,$cycle);

    //nlp_debug_msg('$reports',$reports);
    // Flag the voters with whom the NL asked the survey question.  (There
    // could have been more than one.)
    if($mcid == 102391461) {
      $unique = [];
      foreach ($reports as $voterReports) {
        foreach ($voterReports as $report) {
          $vanid = $report['vanid'];
          $unique[$vanid] = $vanid;
        }
        //nlp_debug_msg('unique count',count($unique));
        //nlp_debug_msg('$unique',$unique);
      }
      //nlp_debug_msg('report count',count($reports));
      //nlp_debug_msg('$reports',$reports);
    }

    $voters = array();
    foreach ($reports as $voterReports) {

      foreach ($voterReports as $report) {
        $vanid = $report['vanid'];
        if(!empty($vanids[$vanid])) {
          $voters[$vanid]['attempt'] = TRUE;
          if($report['type']==$this->reportsObj::SURVEY) {
            $voters[$vanid]['contact'] = TRUE;
          }
        }
      }
    }
    //nlp_debug_msg('$voters',$voters);
    //  Now count the voters with contact attempts or survey responses.
    $contactCount = $attemptCount = 0;
    if(!empty($voters)) {
      foreach ($voters as $voter) {
        if(!empty($voter['attempt'])) {
          $attemptCount++;
          if(!empty($voter['contact'])) {
            $contactCount++;
          }
        }
      }
    }
    // Return the strings for display of voter contact attempts and contacts.
    $progress['attempts'] = $attemptCount;  // Voter contact attempts.
    $progress['contacts'] = $contactCount; // Voter contacts.
    $progress['voterCount'] = $voterCount;
    //nlp_debug_msg('$progress',$progress);
    return $progress;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_options_display
   *
   * @param $hdOptions
   * @param $hdNew
   * @param $sortable
   * @return array
   */
  function nlp_options_display($hdOptions,$hdNew,$sortable): array
  {
    $form_element['options-start'] = array(
      '#children' => " \n".'<div class="big-box">'." \n  ".'<div class="x-small-box-left">' );
   
    $form_element['hd-select'] = array(
      '#type' => 'select',
      '#options' => $hdOptions,
      '#default_value' => $hdNew,
    );
    
    $form_element['space1'] = array(
      '#children' => " \n".'</div>'." \n  ".'<div class="button-box-left">',
    );
    
    // add a submit button
    $submit_name = 'hd-submit';
    $form_element['hd-submit'] = array(
      '#name' => $submit_name,
      '#type' => 'submit',
      '#value' => 'Display the selected HD',
      '#suffix' => " \n  ".'</div>',
    );
  
    $form_element['sort-option'] = $this->nlp_sort_display($sortable);
  
    $form_element['end-options'] = array(
      '#children' => " \n".'<div class="end-big-box"></div></div>',
    );
    
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_sort_display
   *
   * @param $sortable
   * @return array
   */
  function nlp_sort_display($sortable): array
  {
  
    $form_element['sort-start'] = array(
      '#children' => " \n  ".'<div class="sort-box-left">' );
  
    $form_element['sort-select'] = array(
      '#type' => 'select',
      '#options' => $sortable,
      //'#prefix' => '<div  style="float:right;">',
      '#suffix' => '</div>',
    );
  
    $form_element['sort-space'] = array(
      '#children' => " \n  ".'<div class="button-box-left">' );
    
    $form_element['sort-submit'] = array(
      '#name' => 'sort-submit',
      '#type' => 'submit',
      '#value' => 'sort by the selected column',
      //'#prefix' => '<div  style="float:right;">',
      '#suffix' => '</div>',
    );
   
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_build_nls_table
   *
   * This function builds an HTML table using the array of information about
   * the NLs found in $form_state.
   *
   * @param  $nlRecords
   * @param  $lists
   * @return array - $form - form elements for the table.
   */
  function nlp_build_nls_table($nlRecords,$lists): array
  {
    // Create the AJAX wrapper for updating the form changes.
    $form_element['nls-form'] = array(
      '#prefix' => '<div id="nls-form-div">',
      '#suffix' => '</div>',
      '#type' => 'fieldset',
      '#attributes' => array('style' => array('background-image:none;border:0px;
    padding:0px;margin:0px;background-color:rgb(255,255,255);'), ),
    );
    
    // Start the table.
    $form_element['nls-form']['table_start'] = array('#markup' => " \r ".
      '<table class="table-border no-white table-font">',
    );
    // Now construct the header information for each column title.  Uses the
    // table element.
    $hdrRow  = " \n  ".'<th>HD</th>';
    $hdrRow .= " \n  ".'<th>Pct</th>';
    $hdrRow .= " \n  ".'<th>Name-MCID</th>';
    $hdrRow .= " \n  ".'<th>Address</th>';
    $hdrRow .= " \n  ".'<th>Email-Phone</th>';
    $hdrRow .= " \n  ".'<th>Notes</th>';
    $hdrRow .= " \n  ".'<th>NL</th>';
    $hdrRow .= " \n  ".'<th>TC</th>';
    $hdrRow .= " \n  ".'<th>TD</th>';
    $hdrRow .= " \n  ".'<th>CO</th>';
    $hdrRow .= " \n  ".'<th>LI</th>';
    $hdrRow .= " \n  ".'<th>Attempts</th>';
    $hdrRow .= " \n  ".'<th>P2V</th>';
    $hdrRow .= " \n  ".'<th>Voters</th>';
    
    
    // Create the header row.
    $form_element['nls-form']['header_row'] = array(
      '#children' => " \n <thead> \n <tr>".$hdrRow." \n </tr> \n </thead> ",
    );
    // Start the table body.
    $form_element['nls-form']['nl-body-start'] = array(
      '#children' => " \n ".'<tbody>',
    );
    // Display a row for each NL in the list.
    $ask = $lists['askList'];
    $contact = $lists['contactList'];
    $row = 0;
    foreach ($nlRecords as $mcid => $nlRecord)
    {
  
    // Use the Drupal class for odd/even table rows and start the row.
      if($row%2 == 0) {
        $row_style = " \n ".'<tr class="odd">';
      } else {
        $row_style = " \n ".'<tr class="even">';
      }
      $form_element['nls-form']["row-start$row"] = array('#markup' => $row_style,);
      //$hdValue = '<span style="font-weight:bold;">'.$nlRecord['hd'].'</span>';
      $cell = " \n ".'<td>'.$nlRecord['hd'].'</td>';
      $form_element['nls-form']['TX-'.$mcid.'-hd'] = array(
        '#markup' => $cell,
      );
      //$hdValue = '<span style="font-weight:bold;">'.$nlRecord['precinct'].'</span>';
      $cell = " \n ".'<td>'.$nlRecord['precinct'].'</td>';
      $form_element['nls-form']['TX-'.$mcid.'-pct'] = array(
        '#markup' => $cell,
      );
      //$leader_col = '<span style="font-weight:bold;">'.$nlRecord['lastName'].",".
      //  $nlRecord['nickname'].'</span><br>'.$mcid;
      $cell = " \n ".'<td>'.$nlRecord['lastName'].",".$nlRecord['nickname'].'<br>'.$mcid.'</td>';
      $form_element['nls-form']['TX-'.$mcid.'-name'] = array(
        '#markup' => $cell,
      );
      $address_col = str_replace(',', '<br>', $nlRecord['address']);
      $cell = " \n ".'<td>'.$address_col.'</td>';
      $form_element['nls-form']['TX-'.$mcid.'-address'] = array(
        '#markup' => $cell,
      );
      $email_col = $nlRecord['email'].'<br>'.$nlRecord['phone'];
      $cell = " \n ".'<td>'.$email_col.'</td>';
      $form_element['nls-form']['TX-'.$mcid.'-email'] = array(
        '#markup' => $cell,
      );
      $note_default = $nlRecord['status']['notes'];
      //$notesWrap = $nlsObj::NOTES_WRAP;
      $notesWrap = 150;
      $wrap = wordwrap($note_default,$notesWrap,"\n",true);
      $form_element['nls-form']['TB-'.$mcid.'-notes'] = array(
        //'#type' => 'textarea',
        '#type' => 'textarea',
        '#attributes' => array('class' => array('textarea-width no-white')),
        '#cols' => 10,
        '#rows' => 2,
        '#resizable' => FALSE,
        '#prefix' => " \n ".'<td>',
        '#suffix' => '</td>',
        '#ajax' => array(
          'callback' => '::nlp_textbox_callback',
          'wrapper' => 'nls-form-div',
          'disable-refocus' => TRUE,
        ),
        '#default_value' => $wrap,
      );
      $currentAsk = $nlRecord['status']['asked'];
      $defaultAsk = array_search($currentAsk,$ask);
      //nlp_debug_msg('$defaultAsk',$defaultAsk);
      $form_element['nls-form']["AS-".$mcid.'-ask'] = array(
        '#type' => 'select',
        '#options' => $ask,
        '#prefix' => " \n ".'<td>',
        '#suffix' => '</td>',
        '#ajax' => array(
          'callback' => '::nlp_selectbox_callback',
          'wrapper' => 'nls-form-div',
          'disable-refocus' => FALSE,
          //'#event' => 'change',
        ),
        '#default_value' => $defaultAsk,
      );
      $default_tc = ($nlRecord['status']['turfCut']=='Y')?1:0;
      $form_element['nls-form']["TC-".$mcid.'-turf-cut'] = array (
        '#type' => 'checkbox',
        '#default_value' => $default_tc,
        '#prefix' => " \n ".'<td>',
        '#suffix' => '</td>',
        '#ajax' => array(
          'callback' => '::nlp_checkbox_callback',
          'wrapper' => 'nls-form-div',
          'disable-refocus' => FALSE,
        ),
      );
      $default_td = ($nlRecord['status']['turfDelivered']=='Y')?1:0;
      $form_element['nls-form']["TD-".$mcid.'-turf-delivered'] = array (
        '#type' => 'checkbox',
        '#default_value' => $default_td,
        '#prefix' => " \n ".'<td>',
        '#suffix' => '</td>',
        '#ajax' => array(
          'callback' => '::nlp_checkbox_callback',
          'wrapper' => 'nls-form-div',
          'disable-refocus' => FALSE,
        ),
      );
      $currentContact = $nlRecord['status']['contact'];
      $defaultContact = array_search($currentContact,$contact);
      //$co_default = $nlRecord['status']['contact'];
      $form_element['nls-form']["CO-".$mcid.'-contact'] = array(
        '#type' => 'select',
        '#options' => $contact,
        '#prefix' => " \n ".'<td>',
        '#suffix' => '</td>',
        '#ajax' => array(
          'callback' => '::nlp_selectbox_callback',
          'wrapper' => 'nls-form-div',
          'disable-refocus' => FALSE,
          //'#event' => 'change',
        ),
        '#default_value' => $defaultContact,
      );
      
      $cell = " \n ".'<td>'.$nlRecord['status']['loginDate'].'</td>';
      $form_element['nls-form']['TX-'.$mcid.'-login'] = array(
        '#markup' => $cell,
      );
      $cell = " \n ".'<td>'.$nlRecord['progress']['attempts'].'</td>';
      $form_element['nls-form']['TX-'.$mcid.'-attempts'] = array(
        '#markup' => $cell,
      );
      $cell = " \n ".'<td>'.$nlRecord['progress']['contacts'].'</td>';
      $form_element['nls-form']['TX-'.$mcid.'-contacts'] = array(
        '#markup' => $cell,
      );
      $cell = " \n ".'<td>'.$nlRecord['progress']['voterCount'].'</td>';
      $form_element['nls-form']['TX-'.$mcid.'-voters'] = array(
        '#markup' => $cell,
      );

      // End of row.
      $form_element['nls-form']["row-end$row"] = array(
        '#markup' => " \n ".'</tr>',
      );
      $row++;
    }
    // End of table body.
    $form_element['nls-form']['nl-body-end'] = array(
      '#markup' => " \n ".'</tbody>',
    );
    
    // End of the table.
    $form_element['nls-form']['table_end'] = array(
      '#markup' => " \n ".'</table>'." \n ",
    );
    
    
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_sort_nls
   *
   * @param $nlRecords
   * @param $columnKey
   * @return array
   */
  function nlp_sort_nls($nlRecords,$columnKey): array
  {
    //nlp_debug_msg('$columnKey',$columnKey);
    //nlp_debug_msg('$nlRecords',$nlRecords);
    usort($nlRecords, function ($a, $b) use ($columnKey) { return strnatcmp($a[$columnKey], $b[$columnKey]);} );
    $rebuilt = array();
    foreach ($nlRecords as $nlRecord) {
      $rebuilt[$nlRecord['mcid']] = $nlRecord;
    }
    //nlp_debug_msg('$rebuilt',$rebuilt);
    return $rebuilt;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_checkbox_callback
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   */
  function nlp_checkbox_callback($form, $unused) {
    //nlp_debug_msg('callback','called');
    return $form['nls_table']['nls-form'];
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_textbox_callback
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   */
  function nlp_textbox_callback($form, $unused) {
    return $form['nls_table']['nls-form'];
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_selectbox_callback
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   */
  function nlp_selectbox_callback($form, $unused) {
    return $form['nls_table']['nls-form'];
  }
  
  
}