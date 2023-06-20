<?php

namespace Drupal\ip_login;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A policy evaluating to static::DENY when a user needs to be logged in by IP.
 */
class IpLoginPageCacheRequestPolicy implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    // Ensure that we don't deliver cached pages for users who can be logged in
    // automatically.
    if ($request->attributes->get('ip_login_uid')) {
      return static::DENY;
    }
  }

}
