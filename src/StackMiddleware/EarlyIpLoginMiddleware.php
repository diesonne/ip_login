<?php

namespace Drupal\ip_login\StackMiddleware;

use Drupal\ip_login\IpLoginController;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Provides a HTTP middleware to implement IP based login.
 *
 * The role of this "early" middleware is to determine if a user can be logged
 * in automatically. If so, a request attribute is set and IpLoginMiddleware
 * does the actual login, because that needs to happen after the Drupal kernel
 * is initialized by \Drupal\Core\StackMiddleware\KernelPreHandle.
 */
class EarlyIpLoginMiddleware implements HttpKernelInterface {

  use ContainerAwareTrait;

  /**
   * The decorated kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The session service name.
   *
   * @var string
   */
  protected $sessionServiceName;

  /**
   * Constructs an EarlyIpLoginMiddleware.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param string $service_name
   *   The name of the session service, defaults to "session".
   */
  public function __construct(HttpKernelInterface $http_kernel, $service_name = 'session') {
    $this->httpKernel = $http_kernel;
    $this->sessionServiceName = $service_name;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    // Bail out early if we already determined that we can not auto-login.
    if ($request->cookies->get('ipLoginAttempted', NULL)) {
      return $this->httpKernel->handle($request, $type, $catch);
    }

    $uid = NULL;
    if ($type === self::MASTER_REQUEST && PHP_SAPI !== 'cli') {
      // Put the current (unprepared) request on the stack so we can initialize
      // the session.
      $this->container->get('request_stack')->push($request);

      $session = $this->container->get($this->sessionServiceName);
      $session->start();
      $uid = $session->get('uid');

      // Remove the unprepared request from the stack,
      // \Drupal\Core\StackMiddleware\KernelPreHandle::handle() adds the proper
      // one.
      $this->container->get('request_stack')->pop();
    }

    // Do nothing if the user is logged in, or if this is not a web request.
    if ($uid || PHP_SAPI === 'cli') {
      return $this->httpKernel->handle($request, $type, $catch);
    }

    // Check the user's IP.
    if ($matched_uid = IpLoginController::checkIpLoginExists($request)) {
      // For clarity about every scenario, use extensive logic.
      $can_login_as_another_user = $request->cookies->get('ipLoginAsDifferentUser', NULL);
      if ($can_login_as_another_user === NULL) {
        // First time login for user, so log in automatically.
        $request->attributes->set('ip_login_uid', $matched_uid);
      }
      elseif ($can_login_as_another_user == FALSE) {
        // User logged out, but is not allowed to use another user, so log in
        // again.
        $request->attributes->set('ip_login_uid', $matched_uid);
      }
      elseif ($can_login_as_another_user == TRUE) {
        // User logged out, and is allowed to login as another user, so do
        // nothing, just stay on this page and wait for user action.
      }
      else {
        // Do automatic login.
        $request->attributes->set('ip_login_uid', $matched_uid);
      }
    }

    $response = $this->httpKernel->handle($request, $type, $catch);

    // If we determined that we can't auto-login the user, set a session cookie
    // so we don't repeat the user IP check for this browser session.
    if (empty($matched_uid)) {
      $response->headers->setCookie(Cookie::create('ipLoginAttempted', 1));
    }
    return $response;
  }

}
