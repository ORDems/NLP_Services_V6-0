<?php

namespace Drupal\nlpservices;

use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal;
//use Drupal\Core\TempStore\PrivateTempStore;

class NlpSessionData
{
  protected PrivateTempStoreFactory$userSession;
  protected DrupalUser $drupalUser;

  public function __construct( $userSession, $drupalUser) {
    $this->userSession = $userSession;
    $this->drupalUser = $drupalUser;
  }

  public static function create(ContainerInterface $container): NlpSessionData
  {
    return new static(
      $container->get('tempstore.private'),
      $container->get('nlpservices.drupal_user'),
    );
  }

  public function getCounty()
  {
    $sessionData = $this->userSession->get('nlpservices.session_data');
    $county = $sessionData->get('County');
    $currentUser = $this->drupalUser->getCurrentUser();
  
    if(empty($county)) {
      //nlp_debug_msg('$currentUser',$currentUser);
      $county = $currentUser['county'];
      $this->setCounty($county);
    }
  
    $userSession = $sessionData->get('userSession');
    $sessionMcid = (empty($userSession['mcid']))?NULL:$userSession['mcid'];
    //nlp_debug_msg('$sessionMcid',$sessionMcid);
    $userMcid = $currentUser['mcid'];
    //nlp_debug_msg('$userMcid',$userMcid);
    if ($sessionMcid != $userMcid) {
      $userSession['mcid'] = $userMcid;
      $this->setUserSession($userSession);
    }
  
    return $county;
  }

  private function setCounty($county) {
    //nlp_debug_msg('set',$county);
    $factory = Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices_session_data');
    //$store = $this->tempStore->get('nlpservices.session_data');
    try {
      $store->set('County', $county);
    } catch (Drupal\Core\TempStore\TempStoreException $e) {
      nlp_debug_msg('Temp storage error',$e->getMessage());
    }
  }

  public function getUserSession($force=FALSE): array
  {
    $sessionData = $this->userSession->get('nlpservices.session_data');
    //$county = $sessionData->get('County');
    $county = $this->getCounty();
  
    //nlp_debug_msg('$county',$county);
    $userSession = $sessionData->get('userSession');
    //nlp_debug_msg('$userSession',$userSession);
    if(!empty($county) AND !empty($userSession['mcid'] AND !$force)) {
      //$userSession = $sessionData->get('userSession');
      if(empty($userSession)) {
        $userSession = [];
      }
      if(empty($userSession['county'])) {
        $userSession['county'] = $county;
      }
      //nlp_debug_msg('$userSession',$userSession);
    } else {
      $userSession = [];
      $currentUser = $this->drupalUser->getCurrentUser();
      $county = $currentUser['county'];
      try {
        $sessionData->set('County', $county);
      } catch (Drupal\Core\TempStore\TempStoreException $e) {
        nlp_debug_msg('Temp store error',$e->getMessage());
      }
      $userSession['county'] = $county;
      $mcid = $currentUser['mcid'];
      $userSession['mcid'] = $mcid;
      try {
        $sessionData->set('mcid', $mcid);
      } catch (Drupal\Core\TempStore\TempStoreException $e) {
        nlp_debug_msg('Temp store error',$e->getMessage());
      }
      $turfs = Drupal::getContainer()->get('nlpservices.turfs');
      $turfArray = $turfs->turfExists($mcid,$county);
      $userSession['turfIndex'] = NULL;
      if (!empty($turfArray)) {
        $turfIndex = $turfArray['turfIndex'];  // First turfIndex.
        $userSession['turfIndex'] = $turfIndex;
      }
      $this->setUserSession($userSession);
      //nlp_debug_msg('$userSession',$userSession);
    }
    return $userSession;

  }

  public function setUserSession($userSession)
  {
    $sessionData = $this->userSession->get('nlpservices.session_data');
    try {
      $sessionData->set('userSession', $userSession);
    } catch (Drupal\Core\TempStore\TempStoreException $e) {
    }
  }

}