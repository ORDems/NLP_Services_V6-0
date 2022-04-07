<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;


class DrupalUser {
  /*
  public array $nlpRoles = array(
    'nl' => 'nlp_leader',
    'co' => 'nlp_coordinator',
    'admin' => 'nlp_admin',
    //'authenticated' => 'authenticated user',
  );
  */
/*
  public array $nlpRoles = [  // These are id and label pairs.
    NLP_ADMIN_ROLE_ID => NLP_ADMIN_ROLE_LABEL,
    NLP_COORDINATOR_ROLE_ID => NLP_COORDINATOR_ROLE_LABEL,
    NLP_LEADER_ROLE_ID => NLP_LEADER_ROLE_LABEL,
  ];
*/
  private array $searchFields = array(
    'mcid'=>'field_mcid',
    'firstName'=>'field_first_name',
    'lastName'=>'field_last_name',
    'userName'=>'name',
    'email'=>'mail',
  );
  
  protected MagicWord $magicWord;

  public function __construct ($magicWord) {
    $this->magicWord = $magicWord;
  }
  
  public static function create(ContainerInterface $container): DrupalUser
  {
    return new static(
      $container->get('nlpservices.magic_word'),
    );
  }

  /** @noinspection PhpUndefinedFieldInspection */
  private function extractUserInfo($user): array
  {
    $roles = $user->getRoles();
    $uid = $user->id();
    $userInfo['uid'] = $uid;
  
    $userInfo['email'] = $user->getEmail();
    $userInfo['userName'] = $user->getAccountName();
    $userInfo['roles'] = $roles;
    $userInfo['access'] = $user->getLastAccessedTime();
    $userEntity = User::load($uid);
    $userInfo['mcid'] = $userEntity->field_mcid->value;
    $userInfo['firstName'] = $userEntity->field_first_name->value;
    $userInfo['lastName'] = $userEntity->field_last_name->value;
    $userInfo['phone'] = $userEntity->field_phone->value;
    $userInfo['county'] = $userEntity->field_county->value;
    $userInfo['sharedEmail'] = $userEntity->field_shared_email->value;
    $userInfo['turfAccess'] = $userEntity->field_turf_access->value;
    $userInfo['created'] = $userEntity->created->value;
    $userInfo['login'] = $userEntity->login->value;
    return $userInfo;
  }
  
  public function getCurrentUser(): array
  {
    $userCurrent = Drupal::currentUser();
    return $this->extractUserInfo($userCurrent);
  }
  
  public function isNlpAdminUser($user=NULL): bool
  {
    if(empty($user)) {
      $user = $this->getCurrentUser();
    }
    //nlp_debug_msg('$user',$user);
    $userRoles = $user['roles'];
    //nlp_debug_msg('$userRoles',$userRoles);
    if(in_array(NLP_ADMIN_ROLE_ID,$userRoles)) {return TRUE;}
    if(in_array('administrator',$userRoles)) {return TRUE;}
    return FALSE;
  }
  
  public function getUserByMcid($mcid): ?array
  {
    try {
      $userMcid = Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['field_mcid.value' => $mcid,]);
    } catch (Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException $e) {
    } catch (Drupal\Component\Plugin\Exception\PluginNotFoundException $e) {
      nlp_debug_msg('Get user error', $e->getMessage());
      return NULL;
    }
    if(empty($userMcid)) {return NULL;}
    $user = reset($userMcid);
    return $this->extractUserInfo($user);
  }
  
  public function getUserByName($userName): ?array
  {
    $user = user_load_by_name($userName);
    if(empty($user)) {return NULL;}
    return $this->extractUserInfo($user);
  }
  
  public function getUserByEmail($email): array
  {
    $user = user_load_by_mail($email);
    if(empty($user)) {return [];}
    return $this->extractUserInfo($user);
  }

  public function getUserByUid($uid): array
  {
    $user = User::load($uid);
    if(empty($user)) {return [];}
    return $this->extractUserInfo($user);
  }
  
  public function addUser($user): array
  {
    if(empty($user['userName'])) {
      $userByName['status'] = 'no userName';
      return $userByName;
    }
    $userName = $user['userName'];
    $userByName = $this->getUserByName($userName);
    if(!empty($userByName)) {
      $userByName['status'] = 'exists';
      return $userByName;
    }
    if(empty($user['email'])) {
      $userByName['status'] = 'no email';
      return $userByName;
    }
    
    $email = $user['email'];
    $sharedEmail = NULL;
    if(!empty($user['sharedEmail'])) {
      $sharedEmail = $user['sharedEmail'];
    }
    $userByEmail = $this->getUserByEmail($email);
    if(!empty($userByEmail)) {
      $sharedEmail = $email;
      $email = 'shared_'.$email;
    }
    $roles = $user['roles'];
    $drupalRoles = user_role_names();
    //nlp_debug_msg('$drupalRoles',$drupalRoles);
    $roleIds = array_flip($drupalRoles);
    
    $account = [
      'name' => $userName,
      'pass' => $user['magicWord'],
      'mail' => $email,
      'init' => $email,
      'preferred_admin_langcode' => 'en',
      'timezone' => date_default_timezone_get(),
    ];
  
    $newUser = User::create($account);
    $newUser->set("field_first_name", $user['firstName']);
    $newUser->set("field_last_name", $user['lastName']);
    $newUser->set("field_county", $user['county']);
    $newUser->set("field_phone", $user['phone']);
    $newUser->set("field_shared_email", $sharedEmail);
    $accessDate = (!empty($user['access']))?date('Y-m-d\TH:i:s' ,$user['access']):NULL;
    $turfAccess = (!empty($user['turfAccess']))?$user['turfAccess']:$accessDate;
    //nlp_debug_msg('$turfAccess',$turfAccess);
    $newUser->set("field_turf_access", $turfAccess);
    $mcid = (!empty($user['mcid']))?$user['mcid']:NULL;
    $newUser->set("field_mcid", $mcid);
    $newUser->activate();
  
    //nlp_debug_msg('$roles',$roles);
    foreach ($roles as $roleName) {
      if(!empty($roleIds[$roleName])) {
        $roleId = $roleIds[$roleName];
        $newUser->addRole($roleId);
      }
    }

    try {
      $newUser->save();
    } catch (EntityStorageException $e) {
      nlp_debug_msg('User account create error.',$e->getMessage());
      $user['status'] = 'error';
      return $user;
    }
    $user = $this->extractUserInfo($newUser);
    $user['status'] = 'complete';
    return $user;
  }

  /**
   * @param $editUpdate
   * @return array|string
   */
  public function updateUser($editUpdate) {
    $uid = $editUpdate['uid'];
    $user = User::load($uid);
    foreach ($editUpdate as $nlpKey => $nlpValue) {
      switch ($nlpKey) {
        case 'mcid':
          $user->set("field_mcid", $nlpValue);
          break;
        case 'firstName':
          $user->set("field_first_name", $nlpValue);
          break;
        case 'lastName':
          $user->set("field_last_name", $nlpValue);
          break;
        case 'name':
          $user->setUsername($nlpValue);
          break;
        case 'mail':
          $user->setEmail($nlpValue);
          break;
        case 'county':
          $user->set("field_county", $nlpValue);
          break;
        case 'phone':
          $user->set("field_phone", $nlpValue);
          break;
        case 'turfAccess':
          $user->set("field_turf_access", $nlpValue);
          break;
        case 'magicWord':
          $user->setPassword($nlpValue);
          break;
        case 'roles':
          foreach ($nlpValue as $roleId=>$roleLabel) {
            if(empty($roleLabel)) {
              $user->removeRole($roleId);
            } else {
              $user->addRole($roleId);
            }
          }
          break;
      }
    }
  
    try {
      $user->save();
    }
    catch (EntityStorageException $e) {
      nlp_debug_msg('User account update error.',$e->getMessage());
      return $updatedUser['status'] = 'error';
    }
    if(empty($user)) {
      return ['status' => 'error'];
    }
    $updatedUser = $this->extractUserInfo($user);
    $updatedUser['status'] = 'complete';
    return $updatedUser;
  }
  /*
  public function getRoleId($roleName) {
    $roles =  user_roles();
    nlp_debug_msg('$roles',$roles);
    //nlp_debug_msg('$roleName',$roleName);
    //nlp_debug_msg('rid',array_search($roleName, user_roles()));
    return array_search($roleName, user_roles());
  }
  */
  public function createDrupalAccount($userInfo): bool
  {
    $messenger = Drupal::messenger();
    $serverName = $GLOBALS['_SERVER']['SERVER_NAME'];
    $serverNameParts = explode('.',$serverName);
    if(empty($serverNameParts[2])) {  // probably  a MAC.
      $emailServer = $serverNameParts[0].'.'.$serverNameParts[1];
    } else {
      $emailServer = $serverNameParts[1].'.'.$serverNameParts[2];
    }
    
    $userArray = $this->getUserByMcid($userInfo['mcid']);
    $user = reset($userArray);  // first one.
    nlp_debug_msg('$user',$user);
    //return TRUE;
    $msgDisplay = '';
    if(empty($user)) {
      $msgDisplay .= 'This NL does not have an account to use to get the turf. An account will be created.';
     
      $lcUsrName = strtolower($userInfo['firstName'].'.'.$userInfo['lastName']);
      $userName = preg_replace('/-|\s+|&#0*39;|\'/', '', $lcUsrName);
      
      if(empty($userInfo['email'])) {
        $email = 'do_not_email_'.$userInfo['firstName'].'_'.$userInfo['lastName'].'@'.$emailServer;
      } else {
        $email = $userInfo['email'];
      }
      
      //$rid = NLP_LEADER_ROLE_ID;
      
      $account = array(
        'userName' => $userName,
        'email' => $email,
        'firstName' => $userInfo['firstName'],
        'lastName' => $userInfo['lastName'],
        'phone' => $userInfo['phone'],
        'county' => $userInfo['county'],
        'mcid' => $userInfo['mcid'],
        'magicWord' => $userInfo['magicWord'],
        'sharedEmail' => NULL,
        'roles' => array(
          'authenticated' => 'authenticated user',
          NLP_LEADER_ROLE_ID => NLP_LEADER_ROLE_LABEL,
        ),
      );
      //nlp_debug_msg('account', $account);
      $newUser = $this->addUser($account);
      $newUserMsg = '';
      switch ($newUser['status']) {
        case 'error':
          $newUserMsg = 'Something went wrong with creating an account.  '
            . 'Please contact NLP tech support';
          break;
        case 'exists':
          $newUserMsg = "The NL's name is already in use.  "
            . 'Please contact NLP tech support';
          break;
        case 'complete':
          $newUserMsg = 'An account was created for this NL.'
            . '<br>Username: '.$newUser['userName']
            . '<br>Password: '.$userInfo['magicWord'];
          break;
      }
      if(!empty($newUserMsg) AND !empty($msgDisplay)) {
        $msgDisplay .= '<br>';
      }
      $msgDisplay .= $newUserMsg;
      if(!empty($msgDisplay)) {
        $messenger->addStatus(t($msgDisplay));
      }
      
      return TRUE;
      
    } else {
      $fieldCheck = array('mcid'=>$userInfo['mcid'],'email'=>$userInfo['email'],
        'phone'=>$userInfo['phone'],'county'=>$userInfo['county'],
        'firstName'=>$userInfo['firstName'],'lastName'=>$userInfo['lastName']);
      $updateUser = FALSE;
      $nameChanged = $emailChanged = FALSE;
      
      $update['uid'] = $user['uid'];
      foreach ($fieldCheck as $nlpKey => $nlpValue) {
        if($user[$nlpKey] != $nlpValue){
          $updateUser = TRUE;
          switch ($nlpKey) {
            case 'mcid':
              $update['mcid'] = $nlpValue;
              $messenger->addStatus(t("The VANID for this NL was changed
              from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'email':
              $update['mail'] = $nlpValue;
              $emailChanged = TRUE;
              $messenger->addStatus(t("The email address for this NL was
              changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'phone':
              $update['phone'] = $nlpValue;
              $messenger->addStatus(t("The phone number for this NL was
              changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'county':
              $update['county'] = $nlpValue;
              $messenger->addStatus(t("The county for this NL was changed
              from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'firstName':
              $update['firstName'] = $nlpValue;
              $nameChanged = TRUE;
              $messenger->addStatus(t("The first name of this NL was
              changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'lastName':
              $update['lastName'] = $nlpValue;
              $nameChanged = TRUE;
              $messenger->addStatus(t("The last name of this NL was
              changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
          }
        }
      }
      if($nameChanged) {
        $messenger->addWarning(t("A name change was made for this NL but
        the username for the login was not changed,  Contact the NLP tech
        support to change the login."));
      }
      if($emailChanged) {
        if(empty($update['firstName'])) {
          $update['firstName'] = $user['firstName'];
        }
        if(empty($update['lastName'])) {
          $update['lastName'] = $user['lastName'];
        }
      }
      if($updateUser) {
        $this->updateUser($update);
      }
      
      $magicWord = $this->magicWord->getMagicWord($user['mcid']);
      // The password is lost, create a replacement.
      if(empty($magicWord)) {
        $replacementMagicWord = $this->magicWord->createMagicWord();
        $this->magicWord->setMagicWord($userInfo['mcid'],$replacementMagicWord);
      }
      $messenger->addStatus(t('An account exists for this NL.'
        . '<br>Username: '.$user['userName']
        . '<br>Password: '.$magicWord));
      return FALSE;
    }
  }

  /** @noinspection PhpUndefinedFieldInspection */
  public function getCounties(): array
  {
    $query = Drupal::entityQuery('user');
    $result = $query->execute();
    $countyNames = [];
    foreach ($result as $uid) {
      try {
        $user = Drupal::entityTypeManager()->getStorage('user')->load($uid);
      } catch (Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException $e) {
      } catch (Drupal\Component\Plugin\Exception\PluginNotFoundException $e) {
        nlp_debug_msg('Get counties error',$e->getMessage());
        return [];
      }
      if(empty($user)) {return [];}
      $county = $user->field_county->value;
      if(!empty($county)) {
        $countyNames[$county] = $county;
      }
    }
    ksort($countyNames);
    return $countyNames;
  }

  /** @noinspection PhpUndefinedFieldInspection */
  public function getUsers($searchCounty=NULL): array
  {
    $query = Drupal::entityQuery('user');
    $result = $query->execute();
    $users = [];
    foreach ($result as $uid) {
      try {
        $user = Drupal::entityTypeManager()->getStorage('user')->load($uid);
      } catch (Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException $e) {
      } catch (Drupal\Component\Plugin\Exception\PluginNotFoundException $e) {
        nlp_debug_msg('Get user error', $e->getMessage());
        return [];
      }
      if(empty($user)) {return [];}
      $county = $user->field_county->value;
      if (empty($searchCounty)) {
        if (empty($county)) {
          $userArray = $this->extractUserInfo($user);
          if($userArray['uid'] > 1) {
            $users[$userArray['uid']] = $userArray;
          }
        }
      } else {
        if ($county == $searchCounty) {
          $userArray = $this->extractUserInfo($user);
          if($userArray['uid'] > 1) {
            $users[$userArray['uid']] = $userArray;
          }        }
      }
    }
    return $users;
  }
  
  public function deleteUser($uid) {
    //user_delete($uid);
    try {
      $userStorage = Drupal::entityTypeManager()->getStorage('user');
    } catch (Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException $e) {
    } catch (Drupal\Component\Plugin\Exception\PluginNotFoundException $e) {
      nlp_debug_msg('Delete user error', $e->getMessage());
      return;
    }
    if(empty($userStorage)) {return;}
    $user = $userStorage->load($uid);
    try {
      $user->delete();
    } catch (EntityStorageException $e) {
      nlp_debug_msg('Delete user error',$e->getMessage());
    }
  }
  
  public function getNlpRoles(): array
  {
    $nlpRoles = array();
    $drupalRoles = user_roles();
    //nlp_debug_msg('$drupalRoles',$drupalRoles);
    foreach ($drupalRoles as $drupalRole) {
      $roleId = $drupalRole->get('id');
      //nlp_debug_msg('$roleId',$roleId);
      $roleLabel = $drupalRole->get('label');
      //nlp_debug_msg('$roleLabel',$roleLabel);
      if(str_contains($roleId,'nlp_')) {
        $nlpRoles[$roleId] = $roleLabel;
      }
    }
    return $nlpRoles;
  }

  public function searchUsers($field,$value): array
  {
    $userArray = array();
    if(empty($this->searchFields[$field])) {return [];}
    $drupalField = $this->searchFields[$field];
    switch ($drupalField) {
      case 'name':
        $user = $this->getUserByName($value);
        $userArray[$user['uid']] = $user;
        break;
      case 'mail':
        $user = $this->getUserByEmail($value);
        if(empty($user)) {break;}
        $userArray[$user['uid']] = $user;
        break;
      default:
        $queryObj = Drupal::entityQuery('user');
        try {
          $queryObj->condition($drupalField, $value, 'CONTAINS')
            ->addMetaData('account', Drupal::entityTypeManager()->getStorage('user')->load(1));
        } catch (Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException $e) {
        } catch (Drupal\Component\Plugin\Exception\PluginNotFoundException $e) {
          nlp_debug_msg('Search user error', $e->getMessage());
          return [];
        }
        $result = $queryObj->execute();
        //nlp_debug_msg('$result',$result);
        $userArray = array();
        if(!empty($result)) {
          foreach ($result as $uid) {
            try {
              $user = Drupal::entityTypeManager()->getStorage('user')->load($uid);
            } catch (Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException $e) {
            } catch (Drupal\Component\Plugin\Exception\PluginNotFoundException $e) {
              nlp_debug_msg('Search user error', $e->getMessage());
              return [];
            }
            if(empty($user)) {return [];}
            $userArray[$uid] = $this->extractUserInfo($user);
          }
        }
        break;
    }
    return $userArray;
  }
}
