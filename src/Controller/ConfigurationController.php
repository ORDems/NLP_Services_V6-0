<?php

namespace Drupal\nlpservices\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\nlpservices\ApiKeyVerification;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigurationController extends ControllerBase {
  
  protected ApiKeyVerification $verifyApiKeysObj;
  
  public function __construct( $verifyApiKeysObj) {
    $this->verifyApiKeysObj = $verifyApiKeysObj;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ConfigurationController
  {
    return new static(
      $container->get('nlpservices.api_key_verification'),
    );
  }
  
  /** @noinspection PhpUnused */
  public function site_configuration(): array
  {
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    $template_path = $modulePath . "/src/Templates/nlp-config.html.twig";
    $template = file_get_contents($template_path);
    $variables = [
      'module' => 'blocker',
    ];
    return
      [
        'description' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => $variables,
        ],
      ];
  }

  /** @noinspection PhpUnused */
  public function manage_nls(): array
  {
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    $template_path = $modulePath . "/src/Templates/manageNls.html.twig";
    $template = file_get_contents($template_path);
    $variables = [
      'module' => 'nlpservices',
    ];
    return
      [
        'description' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => $variables,
        ],
      ];
  }

  /** @noinspection PhpUnused */
  public function manage_turfs(): array
  {
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    $template_path = $modulePath . "/src/Templates/manageTurfs.html.twig";
    $template = file_get_contents($template_path);
    $variables = [
      'module' => 'nlpservices',
    ];
    return
      [
        'description' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => $variables,
        ],
      ];
  }

  /** @noinspection PhpUnused */
  public function manage_nlp_users(): array
  {
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    $template_path = $modulePath . "/src/Templates/manageDrupalAccounts.html.twig";
    $template = file_get_contents($template_path);
    $variables = [
      'module' => 'nlpservices',
    ];
    return
      [
        'description' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => $variables,
        ],
      ];
  }
  
  /** @noinspection PhpUnused */
  public function verify_api_keys(): array
  {
    Drupal::service("page_cache_kill_switch")->trigger();
    return ['#markup' => $this->verifyApiKeysObj->apiKeyVerification(),];
  }
}
