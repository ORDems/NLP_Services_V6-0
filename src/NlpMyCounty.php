<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\user\Entity\User;

class NlpMyCounty
{
  /** @noinspection PhpUndefinedFieldInspection */
  public function myCounty() {
    $factory = Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices.session_data');
    $county = $store->get('County');
    if(!empty($county)) {
      return $county;
    }
    $user = Drupal::currentUser();
    $uid = $user->id();
    $userEntity = User::load($uid);
    $county = $userEntity->field_county->value;
    $store->set('County',$county);
    return $county;
  }
}
