<?php

namespace Drupal\ip_login;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\ip_login\StackMiddleware\EarlyIpLoginMiddleware;
use Drupal\ip_login\StackMiddleware\IpLoginMiddleware;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines the early ip_login middleware dynamically.
 */
class IpLoginServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $config_storage = BootstrapConfigStorageFactory::get();
    $settings = $config_storage->read('ip_login.settings');

    // Register the middlewares only if the auto-login feature is enabled.
    if ($settings['auto_login']) {
      $container->register('ip_login.early_middleware', EarlyIpLoginMiddleware::class)
        // This middleware needs to run before page caching (priority 200).
        ->addTag('http_middleware', ['priority' => 250, 'responder' => TRUE])
        ->addMethodCall('setContainer', [new Reference('service_container')]);

      $container->register('ip_login.middleware', IpLoginMiddleware::class)
        // This middleware runs after the session is initialized (priority 50).
        ->addTag('http_middleware', ['priority' => 30]);

      $container->register('ip_login.page_cache_request_policy', IpLoginPageCacheRequestPolicy::class)
        ->addTag('page_cache_request_policy');
    }
  }

}
