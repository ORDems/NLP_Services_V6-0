<?php

namespace Drupal\nlpservices\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;

class ConfigurationController extends ControllerBase {


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
}
