<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\DrupalUser;
use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\MagicWord;

/**
 * @noinspection PhpUnused
 */
class AddDrupalAccountForm extends FormBase
{
  protected DrupalUser $drupalUser;
  protected NlpNls $nls;
  protected MagicWord $magicWord;
  
  public function __construct( $drupalUser, $nls, $magicWord)
  {
    $this->drupalUser = $drupalUser;
    $this->nls = $nls;
    $this->magicWord = $magicWord;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): AddDrupalAccountForm
  {
    return new static(
      //$container->get('config.factory'),
      $container->get('nlpservices.drupal_user'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.magic_word'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_add_drupal_account_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if (empty($form_state->get('reenter'))) {
      $form_state->set('reenter', TRUE);
      $form_state->set('page', 'addUser');
      $sessionObj = Drupal::getContainer()->get('nlpservices.session_data');
      $county = $sessionObj->getCounty();
      $form_state->set('county',$county);
      $adminRole = $this->drupalUser->isNlpAdminUser();
      $form_state->set('admin',$adminRole);
    }
    
    $county = $form_state->get('county');
    $page = $form_state->get('page');
    //nlp_debug_msg('$page',$page);

    switch ($page) {
      
      case 'addUser':
        $form['county-name'] = [
          '#markup' => "<h1>".$county." County</h1>",
        ];
        $form['needle'] = array(
          '#type' => 'textfield',
          '#title' => t('Search value'),
          '#size' => 32,
          '#maxlength' => 64,
          '#description' => t("Enter a first name, last name, email, or MCID to find the Active NL to get a login."),
          '#required' => TRUE,
        );
        $form['needle-note'] = [
          '#markup' => "<p>The search value need not be a full name, email, or MCID.  It can be an incomplete fragment.</p>",
        ];
        $form['add_submit'] = array(
          '#type' => 'submit',
          '#name' => 'add-submit',
          '#value' => t('Search for the user'),
        );
        break;

      case 'userSelect':
        $nlOptions = $loginExists = [];
        foreach($form_state->get('nlList') as $mcid => $nl) {
          $loginExists[$mcid] = FALSE;
          $user = $this->drupalUser->getUserByMcid($mcid);
          $loginNote = '';
          if(!empty($user)) {
            $loginExists[$mcid] = TRUE;
            $loginNote = ' ***';
          }
          $email = $nl['email'];
          if(empty($email)) {
            $email = "[<i>No email</i>]";
          }
          $nlOptions[$mcid] = $nl['firstName'].' '.$nl['lastName']." [".$mcid."] ".$nl['address']." - ".$email.$loginNote;
        }
        //nlp_debug_msg('$nlOptions',$nlOptions);
        $form_state->set('loginExists',$loginExists);
        $form['user_select'] = array(
          '#title' => t('Select the user'),
          '#type' => 'radios',
          '#options' => $nlOptions,
        );

        $form['add_selected'] = array(
          '#type' => 'submit',
          '#name' => 'add-selected',
          '#value' => t('Select User'),
        );
        $form['go_back'] = array(
          '#type' => 'submit',
          '#name' => 'go-back',
          '#value' => t('Not found, go back'),
        );
        break;
      
      case 'displayUserAdd':
        $mcid = $form_state->get('selectedMcid');
        $nl = $this->nls->getNlById($mcid);
        $lcUsrName = strtolower($nl['nickname'].'.'.$nl['lastName']);
        $userName = preg_replace('/-|\s+|&#0*39;|\'/', '', $lcUsrName);

        if(empty($nl['email'])) {
          $serverName = $GLOBALS['_SERVER']['SERVER_NAME'];
          $serverNameParts = explode('.',$serverName);
          if(empty($serverNameParts[2])) {  // probably  a MAC.
            $emailServer = $serverNameParts[0].'.'.$serverNameParts[1];
          } else {
            $emailServer = $serverNameParts[1].'.'.$serverNameParts[2];
          }
          $email = 'do_not_email_'.$nl['firstName'].'_'.$nl['lastName'].'@'.$emailServer;
        } else {
          $email = $nl['email'];
        }
        
        $user = [
          'uid' => NULL,
          'email' => $email,
          'userName' => $userName,
          'firstName' => $nl['firstName'],
          'lastName' => $nl['lastName'],
          'phone' => $nl['phone'],
          'mcid' => $mcid,
          'password' => $this->magicWord->createMagicWord(),
          'county' => $nl['county'],
        ];
        //nlp_debug_msg('$user',$user);
        $form['adduser'] = $this->addUser($user);

        if(empty($nl['email'])) {
          $form['email_note'] = [
            '#markup' => "<p>This NL does not have an email.  It is not a good idea to create a login without a working 
            email.  A fictitious one is suggested here if you choose to create an account.</p>"
          ];
        }
        $form['add_user_submit'] = array(
          '#type' => 'submit',
          '#name' => 'add-user-submit',
          '#value' => t('Add User'),
        );
        $form['go_back2'] = array(
          '#type' => 'submit',
          '#name' => 'go-back2',
          '#value' => t('Go back'),
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
    $county = $form_state->get('county');
    
    $triggering_element = $form_state->getTriggeringElement();
    $element_clicked = $triggering_element['#name'];
    switch ($element_clicked) {
      
      case 'add-submit':
        $needle = $form_state->getValue('needle');
        $nlList = $this->nls->searchNls($county,$needle);
        //nlp_debug_msg('$nlList',$nlList);
        if(empty($nlList)) {
          $messenger->addStatus('No NLs found.');
          return;
        }
        $form_state->set('nlList', $nlList);
        $form_state->set('page', 'userSelect');
        return;

      case 'go-back':
      case 'go-back2':
      $form_state->set('page', 'addUser');
        break;

      case 'add-selected':
        $selectedMcid = $form_state->getValue('user_select');
        $loginExists = $form_state->get('loginExists');
        if($loginExists[$selectedMcid]) {
          $messenger->addWarning('This NL already has an account.');
          break;
        }

        $form_state->set('page', 'displayUserAdd');
        $form_state->set('selectedMcid',$selectedMcid);
        break;

      case 'add-user-submit':
        $form_state->set('page', 'addUser');
        $firstName = str_replace("'", "&#039;", trim ( $form_state->getValue('firstName')));
        $lastName = str_replace("'", "&#039;", trim ( $form_state->getValue('lastName')));
        $magicWord = $form_state->getValue('password');
        $mcid = $form_state->getValue('mcid');

        $allowedRoles = $this->drupalUser->getNlpRoles();
        $roleSelection = $form_state->getValue('roles');
        //nlp_debug_msg('$roleSelection',$roleSelection);
        $roles = [];
        foreach($roleSelection as $role) {
          if(!empty($role)) {
            $roles[$role] = $allowedRoles[$role]; // Restore role name.
          }
        }
        $account = array(
          'userName' => $form_state->getValue('username'),
          'email' => $form_state->getValue('email'),
          'firstName' => $firstName,
          'lastName' => $lastName,
          'phone' => $form_state->getValue('phone'),
          'county' => $form_state->get('county'),
          'mcid' => $mcid,
          'magicWord' => $magicWord,
          'roles' => $roles,
        );
        //nlp_debug_msg('$account',$account);
        $newUser = $this->drupalUser->addUser($account);
        //nlp_debug_msg('$newUser',$newUser);
        switch ($newUser['status']) {
          case 'error':
            $messenger->addError(t('Something went wrong with creating an account.  Please contact NLP tech support'));
            break;
          case 'exists':
            $messenger->addError(t("The NL's username is already in use. Please choose something else."));
            break;
          case 'complete':
            $this->magicWord->setMagicWord($mcid, $magicWord);
            $msg = 'An account was created for this NL.<br>Username: '.$newUser['userName'].'<br>Password:'.$magicWord;
            $messenger->addStatus(t($msg));
            break;
          case 'no email':
            $messenger->addError(t('The NL must have an email to create a login.'));
            break;
        }
        return;
    }
  }
  

  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * addUser
   *
   * @param $user
   * @return array
   */
  function addUser($user): array
  {
    $form_element['county-name'] = [
      '#markup' => "<h1>".$user['county']." County</h1>",
    ];
    
    $form_element['add'] = array(
      '#title' => 'Add a user account.',
      '#type' => 'fieldset',
    );
    
    $form_element['add']['mcid'] = [
      '#type' => 'hidden',
      '#value' => $user['mcid']
    ];
    
    $firstName = str_replace("&#039;","'",$user['firstName']);
    $form_element['add']['firstName'] = array(
      '#type' => 'textfield',
      '#title' => t('First Name'),
      '#default_value' => $firstName,
      '#size' => 28,
      '#maxlength' => 32,
    );
    
    $lastName = str_replace("&#039;","'",$user['lastName']);
    $form_element['add']['lastName'] = array(
      '#type' => 'textfield',
      '#title' => t('Last Name'),
      '#default_value' => $lastName,
      '#size' => 28,
      '#maxlength' => 32,
    );
    
    $phoneNumber = $user['phone'];
    $form_element['add']['phone'] = array(
      '#type' => 'textfield',
      '#title' => t('Phone Number'),
      '#default_value' => $phoneNumber,
      '#size' => 28,
      '#maxlength' => 32,
    );

    $form_element['add']['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $user['userName'],
      '#size' => 28,
      '#maxlength' => 32,
    );
  
    $form_element['add']['password'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#default_value' => $user['password'],
      '#size' => 28,
      '#maxlength' => 32,
    );
    
    $form_element['add']['email'] = array(
      '#type' => 'textfield',
      '#title' => t('Email'),
      '#default_value' => $user['email'],
      '#size' => 60,
      '#maxlength' => 160,
    );
    
    $allowedRoles = $this->drupalUser->getNlpRoles();
    unset($allowedRoles['nlp_admin']);
    //nlp_debug_msg('$allowedRoles',$allowedRoles);
    $form_element['add']['roles'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Roles'),
      '#options' => $allowedRoles,
    );
    
    return $form_element;
  }
  
}
