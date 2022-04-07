<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Egulias\EmailValidator\EmailValidator;

use Drupal\nlpservices\DrupalUser;
use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\MagicWord;
use Drupal\nlpservices\HtmlText;

/**
 * @noinspection PhpUnused
 */
class LoginCredentialsReminderForm extends FormBase
{
  protected ConfigFactoryInterface $config;
  protected DrupalUser $drupalUser;
  protected NlpNls $nls;
  protected MagicWord $magicWord;
  protected HtmlText $htmlText;
  protected MailManagerInterface $mailManager;
  protected EmailValidator $emailValidator;
  protected LanguageManagerInterface $languageManager;
  
  public function __construct($config, $drupalUser, $nls, $magicWord, $htmlText,$mailManager,
                              $emailValidator, $languageManager)
  {
    $this->config = $config;
    $this->drupalUser = $drupalUser;
    $this->nls = $nls;
    $this->magicWord = $magicWord;
    $this->htmlText = $htmlText;
    $this->mailManager = $mailManager;
    $this->emailValidator = $emailValidator;
    $this->languageManager = $languageManager;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LoginCredentialsReminderForm
  {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.drupal_user'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.magic_word'),
      $container->get('nlpservices.html_text'),
      $container->get('plugin.manager.mail'),
      $container->get('email.validator'),
      $container->get('language_manager'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_login_credentials_reminder_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $messenger = Drupal::messenger();
    
    if (empty($form_state->get('reenter'))) {
      $form_state->set('reenter', TRUE);
      $form_state->set('page', 'displayUsers');
      $form_state->set('hd', 0);
      
      $factory = Drupal::service('tempstore.private');
      $store = $factory->get('nlpservices.session_data');
      $county = $store->get('County');
      $form_state->set('county',$county);
      //nlp_debug_msg('$county',$county);
      
      $config = $this->config('nlpservices.configuration');
      $emailConfiguration = $config->get('nlpservices-email-configuration');
      $form_state->set('notificationEmail',$emailConfiguration['notifications']['email']);
    }
    
    $county = $form_state->get('county');
    $page = $form_state->get('page');
    $users = $this->drupalUser->getUsers($county);
    //nlp_debug_msg('$users',$users);
    $hdOptions = $this->hdsWithUsers($users);
    $form_state->set('hdOptions', $hdOptions);
    
    $form_state->set('users', $users);
    switch ($page) {
      case 'displayUsers':
        $form['county-name'] = [
          '#markup' => "<h1>".$county." County</h1>",
        ];

        if(!empty($form_state->get('search_select'))) {
          $field = $form_state->get('search_select');
          //nlp_debug_msg('$field',$field);
          $form_state->set('search_select', []);
          $value = $form_state->get('search_value');
          //nlp_debug_msg('$value',$value);
          $users = $this->drupalUser->searchUsers($field, $value);
          if(empty($users)) {
            $messenger->addWarning('No matching records.');
          }
        }

        if(!empty($form_state->get('sort_select'))) {
          $field = $form_state->get('sort_select');
          //nlp_debug_msg('$field',$field);
          $form_state->set('sort_select', []);
          usort($users, function ($a, $b) use ($field) {
            return strnatcmp($a[$field], $b[$field]);
          });
          $usersWithKeys = array();
          foreach ($users as $user) {
            $usersWithKeys[$user['uid']] = $user;
          }
          $users = $usersWithKeys;
          unset($usersWithKeys);
        }
        
        $hd = $form_state->get('hd');
        $form['functions'] = $this->userFunctions();
        if(empty($users)) {
          $messenger->addError(t('No users selected'));
          break;
        }
        $form['users'] = $this->displayUsers($users,$hd);
        $form['reminder_select'] = array(
          '#type' => 'submit',
          '#name' => 'reminder_select',
          '#value' => t('Remind selected users of login credentials.'),
        );
        break;
    }

    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    $form_state->set('reenter', TRUE);
    $form_state->setRebuild();
    $triggering_element = $form_state->getTriggeringElement();
    $element_clicked = $triggering_element['#name'];
    //nlp_debug_msg('$element_clicked',$element_clicked);
    switch ($element_clicked) {

      case 'search_submit':
        $form_state->set('search_select', $form_state->getValue('search_select'));
        $form_state->set('search_value', $form_state->getValue('search_value'));
        return;
      case 'sort_submit':
        $form_state->set('sort_select', $form_state->getValue('sort_select'));
        return;

      case 'reminder_select':
        $tableSelections = $form_state->getValue('table');
        //nlp_debug_msg('$tableSelections',$tableSelections);
        $uids = array();
        foreach ($tableSelections as $value) {
          if(!empty($value)) {
            $uids[] = $value;
          }
        }
        if(empty($uids)) {
          $messenger->addError(t('There were no users selected.'));
          return;
        }
        
        $currentUser = $this->drupalUser->getCurrentUser();
        
        foreach($uids as $uid) {
          $user = $this->drupalUser->getUserByUid($uid);
          //nlp_debug_msg('$user',$user);
          $mcid = $user['mcid'];
          if(empty($mcid)) {
            $password = 'unknown';
          } else {
            $password = $this->magicWord->getMagicWord($mcid);
            if(empty($password)) {
              $password = 'unknown';
            }
          }
          
          //nlp_debug_msg('$user',$user);
          $account['county'] = $user['county'];
          $account['userName'] = $user['userName'];
          $account['magicWord'] = $password;
          $account['firstName'] = $user['firstName'];
          $account['lastName'] = $user['lastName'];
          $account['email'] = $user['email'];
          $account['roles'] = $user['roles'];
          $account['notificationEmail'] = $form_state->get('notificationEmail');
          //nlp_debug_msg('$account',$account);
          $result = $this->remindUserCredentials($account, $currentUser);
          if($result) {
            $messenger->addStatus(t('A reminder email was sent to '
              .$user['firstName'].' '.$user['lastName'].'.'));
          } else {
            $messenger->addStatus(t('The reminder email to '
              .$user['firstName'].' '.$user['lastName'].' failed.'));
          }
        }
    }
  }
  
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * hdsWithUsers
   *
   * @param $users
   * @return array
   */
  function hdsWithUsers(&$users): array
  {
    $hdOptions = array();
    foreach ($users as $uid => $user) {
      $users[$uid]['hd'] = '';
      if(!empty($user['mcid'])) {
        $nl = $this->nls->getNlById($user['mcid']);
        //nlp_debug_msg('$nl',$nl);
        if(!empty($nl)) {
          $users[$uid]['hd'] = $nl['hd'];
          $hdOptions[$nl['hd']] = $nl['hd'];
        }
      }
    }
    return $hdOptions;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * userFunctions
   *
   * @return array
   */
  function userFunctions(): array
  {
    $sortable = array('hd'=>'HD','mcid'=>'MCID','firstName'=>'First Name','lastName'=>'Last Name',
      'userName'=>'Username','email'=>'Email');
    $searchable = $sortable;
    unset($searchable['hd']);
    
    $form_element['search'] = array(
      '#title' => 'Search or Sort',
      '#type' => 'fieldset',
    );
    
    $form_element['search']['search_select'] = array(
      '#type' => 'select',
      '#options' => $searchable,
      '#title' => 'Select a column to search',
      //'#prefix' => t("<div>"),
      '#prefix' => t('<div class="big-box"><div class="small-box-left">'),
      '#suffix' => t('</div>'),
    );
    
    $form_element['search']['search_value'] = array(
      '#type' => 'textfield',
      '#title' => 'Enter a search value',
      '#prefix' => t('<div class="medium-box-left">'),
      '#suffix' => t('</div>'),
    );
    $form_element['search']['search_submit'] = array(
      '#name' => 'search_submit',
      '#type' => 'submit',
      '#value' => 'Search',
      '#prefix' => t('<div class="submit-box-left">'),
      '#suffix' => t('</div></div><div class="end-big-box"></div>'),
      //'#suffix' => t('</div>'),
    );
    
    $form_element['search']['sort_select'] = array(
      '#type' => 'select',
      '#options' => $sortable,
      '#title' => 'Select a column to sort',
      '#prefix' => t('<div class="small-box-left">'),
      '#suffix' => t('</div>'),
    );
    
    $form_element['search']['sort_submit'] = array(
      '#name' => 'sort_submit',
      '#type' => 'submit',
      '#value' => 'Sort',
      '#prefix' => t('<div class="submit-box-left">'),
      '#suffix' => t('</div></div><div class="end-big-box"></div>'),
    );
    
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * displayUsers
   *
   * @param $users
   * @param $hd
   * @return array
   */
  function displayUsers($users,$hd): array
  {
    
    $header = [
      'uid' => 'UID',
      'hd' => t('HD'),
      'mcid' => t('MCID'),
      'lastName' => t('Last Name'),
      'firstName' => t('First Name'),
      'userName' => t('Username'),
      'phone' => t('Phone'),
      'email' => t('Email<br>[Shared Email]'),
      'county' => t('County'),
      'roles' => t('Roles'),
    ];

    $rows = [];
    foreach ($users as $user) {
      $row = [];
      if($hd!=0 AND $hd!=$user['hd']) {continue;}
      $uid = $user['uid'];
      $mcid = $user['mcid'];
      $row['uid'] = $uid;
      $row['hd'] = $user['hd'];
      $row['mcid'] = $mcid;
      $row['lastName'] = $user['lastName'];
      $row['firstName'] = $user['firstName'];
      $row['userName'] = $user['userName'];
      $row['phone'] = $user['phone'];
      
      $email = $user['email'];
      if(!empty($user['sharedEmail'])) {
        $email .= '<br>['.$user['sharedEmail'].']';
      }
      
      $row['email'] = $email;
      $row['county'] = $user['county'];
      
      $roleDisplay = '';
      $roles = $user['roles'];
      foreach ($roles as $roleId => $role) {
        if($roleId != 'authenticated' ) {
          if(!empty($roleDisplay)) {
            $roleDisplay .= ", ";
          }
          $roleDisplay .= $role;
        }
      }
      $row['roles'] = $roleDisplay;


      foreach ($row as $key=>$value) {
        if(empty($value)) {
          $row[$key] = ' ';
        }
      }
      $rows[$uid] = $row;

    }
    //nlp_debug_msg('$rows',$rows);
    //$rows = [];
    $form_element['table'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => t('No users found'),
    );
    
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_remindUserCredentials
   *
   * @param $account
   * @param $currentUser
   * @return bool
   */
  function remindUserCredentials($account,$currentUser): bool
  {
    $messenger = Drupal::messenger();
    
    global $base_url;
    $params['func'] = 'account_reminder';
    $today = date("F j, Y, g:i a");
    $params['subject'] = 'Neighborhood Leader account login reminder'.' - '.$today;
    $message = "<p>".$account['firstName'].",</p>";
    $message .= "<p>".'The following information provides the credentials you need to access NLP Services for '.
      $account['county'].' County'."</p>";
    $message .= '<p><a href="' . $base_url . '" target="_blank">Neighborhood Leader Login</a></p>';
    $message .=  '<p>Username: '.$account['userName'].' <br>Password: '.$account['magicWord'].' </p>';

    $currentUserEmail =  (!empty($currentUser['sharedEmail']))?$currentUser['sharedEmail']:$currentUser['email'];
    $thanks = '<p>Please contact me if you have any questions.<br>Thanks<br>'.$currentUser['firstName'].' '.
      $currentUser['lastName'].'<br>Phone: '.$currentUser['phone'].
      '<br>Email: <a href="mailto:@email?subject=NL%20Help%20Request">'.$currentUserEmail.'</a></p>';
    $message .= $thanks;
    //nlp_debug_msg('params', $params);
    $to = $account['email'];
    $sender = 'NLP Admin<'.$account['notificationEmail'].'>';
    //nlp_debug_msg('$sender',$sender);

    $languageCode = $this->languageManager->getDefaultLanguage()->getId();
    //nlp_debug_msg('$languageCode',$languageCode);
    // Sender's info (used to identify the email if it bounces).
    $params['sender']['firstName'] = $currentUser['firstName'];
    $params['sender']['lastName'] = $currentUser['lastName'];
    $params['sender']['email'] = $currentUserEmail;
    $params['county'] = $account['county'];
    // Recipient's info, ie the NL.
    $params['recipient']['firstName'] = $account['firstName'];
    $params['recipient']['lastName'] = $account['lastName'];
    $params['recipient']['email'] = $to;
  
    //$htmlTextConverter = new Html2Text($message);
    //$assembledMessage = implode('',$message);
    $params['message'] = t($message);

    $this->htmlText->setHtml($message);
    $plainText = $this->htmlText->getText();
    $params['plainText'] = $plainText;
    $params['replyTo'] = $currentUserEmail;
    
    $params['List-Unsubscribe'] = "<mailto: ".$account['notificationEmail']."?subject=unsubscribe>";
    
    //nlp_debug_msg('params',$params);
    $result = $this->mailManager->mail(NLP_MODULE, 'account_reminder', $to, $languageCode, $params, $sender, TRUE);
    //nlp_debug_msg('$result',$result);
    if ($result['result'] != TRUE) {
      $messenger->addError(t('There was a problem sending your message and it was not sent.'));
      return FALSE;
    }
    return TRUE;
  }
  
}