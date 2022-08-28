<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;

use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\NlpTurfs;
use Drupal\nlpservices\NlpPaths;
use Drupal\nlpservices\MagicWord;
use Drupal\nlpservices\DrupalUser;
use Drupal\nlpservices\NlpEncryption;
use Drupal\nlpservices\NlpInstructions;
use Drupal\nlpservices\NlpCoordinators;
use Drupal\nlpservices\NlpTurfDeliveryMessage;
use Drupal\nlpservices\HtmlText;


/**
 * @noinspection PhpUnused
 */
class DeliverTurfForm extends FormBase
{
  protected NlpNls $nlsObj;
  protected NlpTurfs $turfsObj;
  protected NlpEncryption $nlpEncrypt;
  protected DrupalUser $drupalUserObj;
  protected NlpPaths $pathsObj;
  protected FileSystemInterface $fileSystemObj;
  protected NlpInstructions $instructionsObj;
  protected NlpCoordinators $coordinatorsObj;
  protected MagicWord $magicWordObj;
  protected NlpTurfDeliveryMessage $turfMsgObj;
  protected HtmlText $htmlText;
  protected LanguageManagerInterface $languageManager;
  protected MailManagerInterface $mailManager;



  public function __construct($nlsObj, $turfsObj, $nlpEncrypt, $drupalUserObj, $pathsObj, $fileSystemObj,
                              $instructionsObj, $coordinatorsObj, $magicWordObj, $turfMsgObj, $htmlText,
                              $languageManager, $mailManager)
  {
    $this->nlsObj = $nlsObj;
    $this->turfsObj = $turfsObj;
    $this->nlpEncrypt = $nlpEncrypt;
    $this->drupalUserObj = $drupalUserObj;
    $this->pathsObj = $pathsObj;
    $this->fileSystemObj = $fileSystemObj;
    $this->instructionsObj = $instructionsObj;
    $this->coordinatorsObj = $coordinatorsObj;
    $this->magicWordObj = $magicWordObj;    $this->htmlText = $htmlText;
  
    $this->turfMsgObj = $turfMsgObj;
    $this->languageManager = $languageManager;
    $this->mailManager = $mailManager;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DeliverTurfForm
  {
    return new static(
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.turfs'),
      $container->get('nlpservices.encryption'),
      $container->get('nlpservices.drupal_user'),
      $container->get('nlpservices.paths'),
      $container->get('file_system'),
      $container->get('nlpservices.instructions'),
      $container->get('nlpservices.coordinators'),
      $container->get('nlpservices.magic_word'),
      $container->get('nlpservices.turf_delivery_message'),
      $container->get('nlpservices.html_text'),
      $container->get('language_manager'),
      $container->get('plugin.manager.mail'),

    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_deliver_turf_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $messenger = Drupal::messenger();

    if (empty($form_state->get('reenter'))) {
      $form_state->set('selectedHd', 0);
      $form_state->set('selectedPrecinct', 0);
      $factory = Drupal::service('tempstore.private');
      $store = $factory->get('nlpservices.session_data');
      $county = $store->get('County');
      $form_state->set('county', $county);
      $config = Drupal::config('nlpservices.configuration');
      $electionDates = $config->get('nlpservices-election-configuration');
      $form_state->set('electionDates',$electionDates);
      $countyNames = $config->get('nlpservices-county-names');
      $form_state->set('state',$countyNames['State']);
      $emailConfiguration = $config->get('nlpservices-email-configuration');
      $form_state->set('notificationEmail',$emailConfiguration['notifications']['email']);

    }
    $county = $form_state->get('county');
    $selectedHd = $form_state->get('selectedHd');
    //nlp_debug_msg('$selectedHd',$selectedHd);
    $selectedPrecinct = $form_state->get('selectedPrecinct');
    //nlp_debug_msg('$selectedPrecinct',$selectedPrecinct);
    // Get the list of HDs with existing turfs.
    $nlsWithTurfs = $this->turfsObj->getNlsWithTurfs($county);
    //nlp_debug_msg('$nlsWithTurfs',$nlsWithTurfs);
    $precinctsForNls = $this->nlsObj->getDistrictsForNls($nlsWithTurfs);
    //nlp_debug_msg('$districtsForNls',$precinctsForNls);
    ksort($precinctsForNls);
    $hdOptions = array_keys($precinctsForNls);
    //nlp_debug_msg('$hdOptions',$hdOptions);

    $form['precinct-selected'] = array(
      //'#title' => 'Select a list with the turf you want.',
      '#type' => 'fieldset',
      '#prefix' => '<div id="ajax-turf-replace" >',
      '#suffix' => '</div>',
    );

    if ($hdOptions) {
      // House Districts exists.
      if(empty($selectedHd)) {
        //reset($hdOptions);
        $selectedHd = key($hdOptions);
        $form_state->set('selectedHd', $selectedHd);
      }
      //nlp_debug_msg('$selectedHd',$selectedHd);
      $form_state->set('hdOptions', $hdOptions);
      $form['precinct-selected']['hd'] = array(
        '#type' => 'select',
        '#title' => t('House District Number'),
        '#options' => $hdOptions,
        '#default_value' => $selectedHd,
        '#ajax' => array(
          'callback' => '::precinctSelectedCallback',
          'wrapper' => 'ajax-turf-replace',
        )
      );

    }
    // Put a container around both the pct and the NL selection, they both
    // reset and have to be redrawn with a change in the HD.
    $selectedHdName = $hdOptions[$selectedHd];
    //nlp_debug_msg('$selectedHdName',$selectedHdName);
    if (empty($precinctsForNls[$selectedHdName])) {
      $messenger->addWarning("No turfs exist");
      return $form;
    } else {
      // Precinct exists.
      $precincts = $precinctsForNls[$selectedHdName];
      ksort($precincts);

      $precinctOptions = array_keys($precincts);
      //nlp_debug_msg('$precinctOptions',$precinctOptions);
      $form_state->set('pctOptions', $precinctOptions);
      if(empty($selectedPrecinct)) {
        //reset($precinctOptions);
        $selectedPrecinct = key($precinctOptions);
        $form_state->set('selectedPrecinct',$selectedPrecinct);
      }
      //nlp_debug_msg('$selectedPrecinct',$selectedPrecinct);

      $form['precinct-selected']['precinct'] = array(
        '#type' => 'select',
        '#title' => t('Precinct Number'),
        '#options' => $precinctOptions,
        '#default_value' => $selectedPrecinct,

        '#ajax' => array(
          'callback' => '::precinctSelectedCallback',
          'wrapper' => 'ajax-turf-replace',
          'effect' => 'fade',
        ),

      );
    }
    // The user selected a precinct, now create the list of turfs.
    // Get all the turfs for all the NLs in this district.
    $selectedPctName = $precinctOptions[$selectedPrecinct];
    //nlp_debug_msg('$selectedPctName',$selectedPctName);
    $turfArray = [];
    $nlsInDistrict = $precinctsForNls[$selectedHdName][$selectedPctName];
    //nlp_debug_msg('$nlsInDistrict',$nlsInDistrict);
    foreach ($nlsInDistrict as $turfs) {
      foreach ($turfs as $turfIndex => $turf) {
        $turfArray[$turfIndex] = $turf;
      }
    }
    //nlp_debug_msg('$turfArray',$turfArray);
    $columnKey = 'nlLastName';
    usort($turfArray, function ($a, $b) use ($columnKey) { return strnatcmp($a[$columnKey], $b[$columnKey]);} );
    // Display the turf choices.
    $emails = [];
    if (!empty($turfArray)) {
      $form_state->set('turfArray', $turfArray);
      $turfChoices = [];
      foreach ($turfArray as $turfIndex=>$turf) {
        // Create the display for this turf choice.
        $turfDisplay = $turfIndex.' '.$turf['commitDate'].' '
          .$turf['nlFirstName'].' '.$turf['nlLastName'].': '
          .$turf['turfName'].', pct-'.$turf['turfPrecinct'];
        if(empty($turf['turfPDF'])) {
          $turfDisplay .= '&nbsp; ***';
        }
        $turfChoices[$turfIndex] = '['.$turf['email'].'] '.$turfDisplay;
        $emails[$turfIndex]['email'] = $turf['email'];
      }
      $form_state->set('emails',$emails);

      $form['precinct-selected']['turf-select'] = array(
        '#title' => t('Select the NL to to receive the turf and instructions'),
        '#type' => 'radios',
        '#options' => $turfChoices,
        '#required' => TRUE,
      );
    } else {
      $messenger->addWarning('There are no turfs for this selection.');
      return $form;
    }
    $form['footnote'] = array(
      '#type' => 'markup',
      '#markup' => '<p>***  <i>This turf does not have a printable walksheet associated.  If the NL is
                    canvassing, consider doing that now.  The walksheet will be associated with the
                    selected turf.  If the NL has more than one turf, you have to send an email for each
                    turf that needs a walksheet.  It\'s better if you attached the walk sheet when you
                    synced the turf. </i>'
    );
    // Name of the PDF of the walksheet for the turf.
    $form['turf_pdf'] = array(
      '#type' => 'file',
      '#title' => t('NL turf walksheet'),
      '#description' => 'Attach this PDF, XLSX or XLS file to the turf or replace an existing file.',
      '#size' => 75,
    );
    // Allow the sender to add a paragraph to the email.
    $form['note'] = array(
      '#title' => 'Additional note for the NL',
      '#type' => 'textarea',
      '#description' => 'This will add an additional paragraph to the email to be sent to the NL.'
    );
    // Add a submit button to send the email.
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Send an email to the selected NL.',
    );

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->set('reenter',TRUE);
    $triggeringElement = $form_state->getTriggeringElement();
    //nlp_debug_msg('validate - $triggering_element',$triggeringElement);
    $elementClicked = $triggeringElement['#name'];
    //nlp_debug_msg('$element_clicked',$elementClicked);
    switch ($elementClicked) {
      case 'hd';
        $form_state->set('selectedHd',$triggeringElement['#value']);
        $form_state->set('selectedPrecinct',0);
        break;
      case 'precinct';
        $form_state->set('selectedPrecinct',$triggeringElement['#value']);
        break;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    $form_state->set('reenter',TRUE);
    $form_state->setRebuild();
    $county = $form_state->get('county');
    $state = $form_state->get('state');

    $selectedTurfIndex = $form_state->getValue('turf-select');
    $electionDates = $form_state->get('electionDates');

    $turfArray = $form_state->get('turfArray')[$selectedTurfIndex];
    $mcid = $turfArray['mcid'];
    $currentUser = $this->drupalUserObj->getCurrentUser();
    //nlp_debug_msg('$currentUser',$currentUser);
    // Now check that we have the PDF for the turf.
    $pdfFile = $_FILES['files']['name']['turf_pdf'];
    if (!empty($pdfFile )) {
      $pdfFileName = strtolower($pdfFile);
      $pdfFileNameParts = explode('.', $pdfFileName);
      $pdfFileNameType = end($pdfFileNameParts);
      $allowed = array('pdf','xls','xlsx');
      if (!in_array($pdfFileNameType, $allowed)) {
        $messenger->addError('The walksheet must be a PDF, XLS, or XLSX type.');
        return;
      }
      // Save the PDF where we can find it.
      $pdfTempName = $_FILES['files']['tmp_name']['turf_pdf'];
      $pdfUniqueFileName = "MCID".$mcid."_".$pdfFileNameParts[0].'_TI'.$turfArray['turfIndex'].'.'.$pdfFileNameType;
      $uri = $this->pathsObj->getPath('PDF',$county).$pdfUniqueFileName;
      $this->fileSystemObj->moveUploadedFile($pdfTempName, $uri);

      $this->turfsObj->updateTurfFiles('pdf',$pdfUniqueFileName,$turfArray['turfIndex']);
    }

    // Get the optional note from the sender to the NL.
    $note = $form_state->getValue('note');
    $string = new FormattableMarkup($note,[]);
    $note = Html::escape($string);

    $instructions = $this->instructionsObj->getInstructions($county);
    if (empty($instructions['canvass']['fileName']) AND empty($instructions['postcard']['fileName'])) {
      $messenger->addError('You need to upload the canvass or postcard instructions first.');
      return;
    }
    // Get the info about the NL for the email.
    $nl = $this->nlsObj->getNlById($mcid);
    //nlp_debug_msg('$nl',$nl);
    $sender = [
      'firstName' => $currentUser['firstName'],
      'lastName' => $currentUser['lastName'],
      'email' => $currentUser['email'],
    ];

    // Recipient's info, ie the NL.
    $magicWord = $this->magicWordObj->getMagicWord($mcid);
    if(empty($magicWord)) {
      $magicWord = 'Your Password';
    }

    $user = $this->drupalUserObj->getUserByMcid($mcid);
    $userName = $user['userName'];

    $recipient = [
      'firstName' => $nl['nickname'],
      'lastName' => $nl['lastName'],
      'email' => $nl['email'],
      'magicWord' => $magicWord,
      'userName' => $userName,
    ];

    $coordinator = [
      'firstName' => $currentUser['firstName'],
      'lastName' => $currentUser['lastName'],
      'email' => $currentUser['email'],
      'phone' => $currentUser['phone'],
    ];

    $region = [
      'hd'=>$nl['hd'],
      'pct'=>$nl['precinct'],
      'county'=>$county,
    ];
    $region['coordinators'] = $this->coordinatorsObj->getAllCoordinators();
    $nlCoordinator = $this->coordinatorsObj->getCoordinator($region);
    //nlp_debug_msg('$nlCoordinator',$nlCoordinator);
    if (!empty($coordinator)) {
      $coordinator = [
        'firstName' => $nlCoordinator['firstName'],
        'lastName' => $nlCoordinator['lastName'],
        'email' => $nlCoordinator['email'],
        'phone' => $nlCoordinator['phone'],
      ];
    }
    $notificationEmail = $form_state->get('notificationEmail');

    // Construct the message.
    $emailInfo = [
      'state' => $state,
      'county' => $county,
      'notificationEmail' => $notificationEmail,
      'note' => $note,
      'sender' => $sender,
      'recipient' => $recipient,
      'coordinator' => $coordinator,
      'electionDates' => $electionDates,
    ];
    $result = $this->notifyUserCredentials($emailInfo);

    if ($result) {
      // Update the NLs status to indicate the turf was delivered.
      $this->nlsObj->updateNlStatus($mcid,'turfDelivered');
      // Now update date the turf was delivered.
      $this->turfsObj->setTurfDelivered($turfArray['turfIndex']);
      // Done.
      $messenger->addMessage(t('Your message has been sent.'));
    }

  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * precinctSelectedCallback
   *
   * AJAX callback for the selection of an NL to associate with a turf.
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function precinctSelectedCallback($form, $unused) {
    //Rebuild the form to list the NLs in the precinct after the precinct is selected.
    return $form['precinct-selected'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * notifyUserCredentials
   *
   * @param $emailInfo
   * @return bool
   */
  function notifyUserCredentials($emailInfo): bool
  {
    $messenger = Drupal::messenger();
    //nlp_debug_msg('$emailInfo',$emailInfo);
    $serverName = $GLOBALS['_SERVER']['SERVER_NAME'];
    $serverUrl = 'https://'.$serverName;
    $state = $emailInfo['state'];
    $county = $emailInfo['county'];
    $turfMsg = $this->turfMsgObj->getTurfMsg($state,$county);
    //nlp_debug_msg('$turfMsg',strToHex($turfMsg));

    $electionDates = $emailInfo['electionDates'];

    $coordinatorContactTemplate = '<p>@firstName @lastName<br>Phone: @phone<br>Email:
<a href="mailto:@email?subject=Request for help with turf">@email</a></p>';

    /** @noinspection HtmlUnknownTarget */
    $nlpLoginTemplate = '<p><b>Neighborhood Leader Login: </b><a href="@server_url" target="_blank">@server_url</a>
<br><b>Username:</b> @name<br><b>Password:</b> @pass</p>';
    $plainNote = NULL;
    if (!empty($emailInfo['note'])) {
      $plainNote = '<p>' . $emailInfo['note'] . '</p>';
    }

    $needles = array('@nlpLogin','@coordinatorContactInfo','@plainNote');
    $replace = array($nlpLoginTemplate,$coordinatorContactTemplate,$plainNote);

    $paragraphsAdded = str_replace($needles, $replace ,$turfMsg);

    $electionTimeStamp = strtotime($electionDates['nlp_election_date']);
    $ballotDropTimeStamp = strtotime($electionDates['nlp_ballot_drop_date']);

    $message = t($paragraphsAdded, array(
      '@nickname' => $emailInfo['recipient']['firstName'],
      '@county' => $county,
      '@electionDay' => date("F j, Y",$electionTimeStamp),
      '@ballotDropDay' => date("F j, Y",$ballotDropTimeStamp),
      '@server_url' => $serverUrl,
      '@name' => $emailInfo['recipient']['userName'],
      '@pass' => $emailInfo['recipient']['magicWord'],
      '@firstName' => $emailInfo['coordinator']['firstName'],
      '@lastName' => $emailInfo['coordinator']['lastName'],
      '@phone' => $emailInfo['coordinator']['phone'],
      '@email' => $emailInfo['coordinator']['email'],
    ));

    $this->htmlText->setHtml($message);
    $plainText = $this->htmlText->getText();

    $params['func'] = 'deliver_turf';
    $params['subject'] =  t('Neighborhood Leader Materials - @county County',['@county' => $county]);
    $params['message'] = $message;
    $params['sender']['firstName'] = $emailInfo['sender']['firstName'];
    $params['sender']['lastName'] = $emailInfo['sender']['lastName'];
    $params['sender']['email'] = $emailInfo['sender']['email'];
    $params['county'] = $emailInfo['county'];
    $params['recipient']['firstName'] = $emailInfo['recipient']['firstName'];
    $params['recipient']['lastName'] = $emailInfo['recipient']['lastName'];
    $params['recipient']['email'] = $emailInfo['recipient']['email'];
    $params['plainText'] = $plainText;
    $params['replyTo'] = $emailInfo['sender']['email'];

    $params['List-Unsubscribe'] = "<mailto: ".$emailInfo['notificationEmail']."?subject=unsubscribe>";

    $to = $emailInfo['recipient']['email'];
    $languageCode = $this->languageManager->getDefaultLanguage()->getId();
    $sender = $emailInfo['coordinator']['email'];

    //nlp_debug_msg('params',$params);
    $result = $this->mailManager->mail(NLP_MODULE, 'deliver_turf', $to, $languageCode, $params, $sender);
    //nlp_debug_msg('$result',$result);
    if (!$result['result']) {
      $messenger->addError(t('There was a problem sending your message and it was not sent.'));
      return FALSE;
    }
    return TRUE;
  }
}
