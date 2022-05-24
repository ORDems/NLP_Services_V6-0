<?php

namespace Drupal\nlpservices\Controller;

use Drupal\Core\Controller\ControllerBase;
//use Drupal\nlpservices\AwakeSalutation;
//use Drupal\nlpservices\NlpCreateFrontPage;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigurationController extends ControllerBase {


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
