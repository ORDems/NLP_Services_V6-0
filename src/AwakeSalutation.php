<?php

namespace Drupal\nlpservices;

use DateTime;
use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
//use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\DrupalUser;

/**
 * Prepares the salutation to the world.
 */
class AwakeSalutation {
  
  use StringTranslationTrait;
  
  protected ConfigFactoryInterface $config;
  
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config;
  }
  
  public static function create(ContainerInterface $container): AwakeSalutation
  {
    /** @noinspection PhpParamsInspection */
    return new static(
      $container->get('config.factory'),
    );
  }

  public function getSalutation(): Drupal\Core\StringTranslation\TranslatableMarkup
  {

    $messenger = Drupal::messenger();
    $messenger->addStatus('Hello from down under.');

    $drupalUserObj = Drupal::getContainer()->get('nlpservices.drupal_user');

    $isAdmin = $drupalUserObj->isNlpAdminUser();

    $roles = \Drupal\user\Entity\Role::loadMultiple();
    nlp_debug_msg('$roles',$roles);

    $drupalRoles = user_role_names();
    nlp_debug_msg('$drupalRoles',$drupalRoles);

    $time = new DateTime();
    if ((int) $time->format('G') >= 00 && (int) $time->format('G') < 12) {
      return $this->t('Good morning world');
    }
    
    if ((int) $time->format('G') >= 12 && (int) $time->format('G') < 18) {
      return $this->t('Good afternoon world');
    }
    /*
    if ((int) $time->format('G') >= 18) {
      return $this->t('Good evening world');
    }
    */
    return $this->t('Good evening world');
  }
  
}
