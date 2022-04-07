<?php

namespace Drupal\nlpservices\Controller;

use Drupal\Core\Controller\ControllerBase;
//use Drupal\nlpservices\AwakeSalutation;
use Drupal\nlpservices\NlpCreateFrontPage;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigurationController extends ControllerBase {


  public function site_configuration(): array
  {
    $template_path = drupal_get_path('module', 'nlpservices') .
      "/src/Templates/nlp-config.html.twig";
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

  public function manage_nls(): array
  {
    $template_path = drupal_get_path('module', 'nlpservices') .
      "/src/Templates/manageNls.html.twig";
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
    $template_path = drupal_get_path('module', 'nlpservices') .
      "/src/Templates/manageTurfs.html.twig";
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
    $template_path = drupal_get_path('module', 'nlpservices') .
      "/src/Templates/manageDrupalAccounts.html.twig";
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
